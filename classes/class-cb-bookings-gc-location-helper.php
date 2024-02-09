<?php

class CB_Bookings_GC_Location_Helper {

  const ITEM_AVAILABLE = 0; //item is available
  const LOCATION_CLOSED = 1; //regular closed day / special closing day / holiday -> no pickup return
  //const ITEM_BOOKED = 2; //item is booked
  const OUT_OF_TIMEFRAME = 3; //no timeframe for item set

  public static function get_location_days($item_id, $date_start, $date_end) {
    $timeframes = CB_Bookings_GC_Location_Helper::get_timeframes_in_period($item_id, $date_start, $date_end);

    $filter_period = new DatePeriod(new DateTime($date_start), new DateInterval('P1D'), new DateTime($date_end . ' +1 day'));
    $location_days = [];
    foreach( $filter_period as $date) {
      $location_days[] = $date->format('Y-m-d');
    }

    $location_days = array_fill_keys($location_days, ['location_id' => null, 'status' => self::OUT_OF_TIMEFRAME]);

    //mark days in timeframe
    $location_days = self::mark_days_in_timeframe($timeframes, $location_days);

    //mark closing days (of location)
    foreach($timeframes as $timeframe) {
      $location_days = self::mark_closed_days($location_days, $timeframe);
    }

    return $location_days;
  }

  protected static function mark_days_in_timeframe($timeframes, $availability) {
    //prepare date_times for start/end of timeframes
    $timeframe_date_times = [];
    foreach ($timeframes as $timeframe) {
      switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
        case 1:
          $timestamp_date_start = strtotime($timeframe['date_start']);
          $timestamp_date_end = strtotime($timeframe['date_end']);
          $location_id = $timeframe['location_id'];
          break;
        case 2:
          $timestamp_date_start = get_post_meta($timeframe->ID, \CommonsBooking\Model\Timeframe::REPETITION_START, true);
          $timestamp_date_end = get_post_meta($timeframe->ID, \CommonsBooking\Model\Timeframe::REPETITION_END, true);
          $location_id = get_post_meta($timeframe->ID, \CommonsBooking\Model\Timeframe::META_LOCATION_ID, true);
          break;
      }

      $timeframe_date_time_start = new DateTime();
      $timeframe_date_time_start->setTimestamp($timestamp_date_start);
      $timeframe_date_time_end = new DateTime();
      $timeframe_date_time_end->setTimestamp($timestamp_date_end);

      $timeframe_date_times[] = [
        'date_time_start' => $timeframe_date_time_start,
        'date_time_end' => $timeframe_date_time_end,
        'location_id' => $location_id
      ];
    }

    //mark days which are inside a timeframe
    foreach ($availability as $date => $day) {
      $av_date_time = new DateTime();
      $av_date_time->setTimestamp(strtotime($date));
      foreach ($timeframe_date_times as $timeframe_date_time) {
        if($av_date_time >= $timeframe_date_time['date_time_start'] && $av_date_time <= $timeframe_date_time['date_time_end']) {
          $availability[$date] = [
            'status' => self::ITEM_AVAILABLE,
            'location_id' => $timeframe_date_time['location_id']
          ];
        }
      }
    }

    return $availability;
  }

  public static function get_timeframes_in_period( $item_id, $date_start, $date_end ) {

    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        global $wpdb;
        $table_name = $wpdb->prefix . 'cb_timeframes';
        $sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE item_id = %d AND NOT (date_start >= '" . $date_end . "' OR date_end <= '" . $date_start . "') ORDER BY date_start", $item_id);
        $timeframes = $wpdb->get_results($sql, ARRAY_A);
        break;
      case 2:
        $timeframes = self::fetch_cb2_timeframes_in_period($date_start, $date_end, $item_id);
        break;
    }
    return $timeframes;
  }

  static function fetch_cb2_timeframes_in_period($date_start, $date_end, $item_id) {
    $date_start_timestamp = strtotime($date_start);
    $date_end_timestamp = strtotime($date_end) + 24 * 60 * 60 - 1;

    if(class_exists('\CommonsBooking\Wordpress\CustomPostType\Booking')) {
      $args = [
        'post_type' => \CommonsBooking\Wordpress\CustomPostType\Timeframe::getPostType(),
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query'  => [
          'relation' => 'AND',
          [
            'key'     => \CommonsBooking\Model\Timeframe::META_ITEM_ID,
            'value'   => $item_id,
            'compare' => '=',
          ],
          [
            'relation' => 'OR',
            [
              'key'     => \CommonsBooking\Model\Timeframe::REPETITION_START,
              'value'   => $date_end_timestamp,
              'compare' => '<',
              'type'    => 'numeric',
            ],
            [
              'key'     => \CommonsBooking\Model\Timeframe::REPETITION_END,
              'value'   => $date_start_timestamp,
              'compare' => '>',
              'type'    => 'numeric',
            ]
          ]
        ]
      ];
      
      $query = new \WP_Query( $args );
      if ( $query->have_posts() ) {
        $timeframes =  $query->get_posts();
        return $timeframes;
      }
      else {
        return [];
      }
    }
  }

  protected static function mark_closed_days($availability, $timeframe) {
    //regular closed days of location
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        $date_start_timestamp = strtotime($timeframe['date_start']);
        $date_end_timestamp = strtotime($timeframe['date_end']);
        $location_id = $timeframe['location_id'];

        $cb_data = new CB_Data();
        $location = $cb_data->get_location($location_id);
        $regular_closed_weekdays = is_array($location['closed_days']) ? $location['closed_days'] : [];
        break;
      case 2:
        $date_start_timestamp = get_post_meta($timeframe->ID, \CommonsBooking\Model\Timeframe::REPETITION_START, true);
        $date_end_timestamp = get_post_meta($timeframe->ID, \CommonsBooking\Model\Timeframe::REPETITION_END, true);
        $location_id = get_post_meta($timeframe->ID, \CommonsBooking\Model\Timeframe::META_LOCATION_ID, true);

        $regular_closed_weekdays = CB2_Restriction_Service::get_regular_closed_weekdays($timeframe->ID);
        break;
    }

    //trigger_error($location_id . ': ' .json_encode($regular_closed_weekdays));
    
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        // if special days plugin available: fetch special closing days & holidays
        $cb_special_days_path = cb_iur\get_active_plugin_directory('commons-booking-special-days.php');
        $special_closed_days_timestamps = $cb_special_days_path ? CB_Special_Days::get_locations_special_closed_days($location_id, $date_start_timestamp, $date_end_timestamp) : []; 
        break;

      case 2:
        //TODO: CB2 holidays
        $special_closed_days_timestamps = [];
        break;
    }

    foreach ($availability as $date => $day) {
      if($day['status'] != self::OUT_OF_TIMEFRAME) {
        $av_date_time = new DateTime();
        $av_date_time->setTimestamp(strtotime($date));

        foreach ($regular_closed_weekdays as $regular_closed_weekday) {

          //availability date falls on a regular closed day
          if($regular_closed_weekday == date("N", $av_date_time->getTimestamp())) {
            $availability[$date]['status'] = self::LOCATION_CLOSED;
          }
        }

        //availability date falls on a special closed day / holiday
        foreach ($special_closed_days_timestamps as $special_closed_days_timestamp) {
          if($date == date('Y-m-d', $special_closed_days_timestamp)) {
            $availability[$date]['status'] = self::LOCATION_CLOSED;
          }
        }
      }
    }
    

    return $availability;
  }
}

?>
