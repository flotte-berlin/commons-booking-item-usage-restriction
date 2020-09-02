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
      $location_days = self::mark_closed_days($timeframe['location_id'], $location_days, $timeframe['date_start'], $timeframe['date_end']);
    }

    return $location_days;
  }

  protected static function mark_days_in_timeframe($timeframes, $availability) {
    //prepare date_times for start/end of timeframes
    $timeframe_date_times = [];
    foreach ($timeframes as $timeframe) {
      $timeframe_date_time_start = new DateTime();
      $timeframe_date_time_start->setTimestamp(strtotime($timeframe['date_start']));
      $timeframe_date_time_end = new DateTime();
      $timeframe_date_time_end->setTimestamp(strtotime($timeframe['date_end']));

      $timeframe_date_times[] = [
        'date_time_start' => $timeframe_date_time_start,
        'date_time_end' => $timeframe_date_time_end,
        'location_id' => $timeframe['location_id']
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
    global $wpdb;

    $table_name = $wpdb->prefix . 'cb_timeframes';
    $sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE item_id = %d AND NOT (date_start >= '" . $date_end . "' OR date_end <= '" . $date_start . "') ORDER BY date_start", $item_id);
    $timeframes = $wpdb->get_results($sql, ARRAY_A);

    return $timeframes;
  }

  protected static function mark_closed_days($location_id, $availability, $date_start, $date_end) {
    //regular closed days of location
    $cb_data = new CB_Data();
    $location = $cb_data->get_location($location_id);
    $regular_closed_weekdays = is_array($location['closed_days']) ? $location['closed_days'] : [];

    //trigger_error($location_id . ': ' .json_encode($regular_closed_weekdays));

    // if special days plugin available: fetch special closing days & holidays
    $cb_special_days_path = cb_iur\get_active_plugin_directory('commons-booking-special-days.php');
    $special_closed_days_timestamps = $cb_special_days_path ? CB_Special_Days::get_locations_special_closed_days($location_id, strtotime($date_start), strtotime($date_end)) : [];

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
