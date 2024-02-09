<?php

class CB_Bookings_Gantt_Chart_Shortcode {

  private static function validate_input($input) {

    $item_id = (int) $input['item_id'];
    $date_start = isset($input['date_start']) && strlen($input['date_start']) > 0 ? new DateTime($input['date_start']) : null;
    $date_end = isset($input['date_end']) && strlen($input['date_end']) > 0 ? new DateTime($input['date_end']) : null;
    $event = isset($input['event']) && ($input['event'] == 'click' || $input['event'] == 'mouseover') ? $input['event'] : 'click';
    $scrollbar_x_start = isset($input['scrollbar_x_start']) && (float) $input['scrollbar_x_start'] >= 0 && $input['scrollbar_x_start'] <= 1 ? (float) $input['scrollbar_x_start'] : 0;
    $scrollbar_x_end = isset($input['scrollbar_x_end']) && (float) $input['scrollbar_x_end'] >= 0 && $input['scrollbar_x_end'] <= 1 ? (float) $input['scrollbar_x_end'] : 1;

    if(!$date_start) {
      $date_start = new DateTime();
    }

    if(!$date_end) {
      $cb_settings = new CB_Admin_Settings();
      $days_to_show = $cb_settings->get_settings( 'bookings', 'bookingsettings_daystoshow' );

      $date_end = new DateTime();
      $date_end->modify($days_to_show . ' days');
    }

    if($item_id) {

      if($date_start <= $date_end) {

        if(get_post_type($item_id) == CB_Item_Usage_Restriction::get_cb_item_post_type()) {
          //$item = $items[0];

          return [
            'item_id' => $item_id,
            'date_start' => $date_start,
            'date_end' => $date_end,
            'scrollbar_x_start' => $scrollbar_x_start,
            'scrollbar_x_end' => $scrollbar_x_end,
            'event' => $event
          ];
        }

      }
    }
  }

  private static function validate_input_with_nonce($input) {

    $validated_input = self::validate_input($input);

    if($validated_input) {
      $nonce = sanitize_text_field($input['nonce']);

      if(wp_verify_nonce($nonce, 'cb_bookings_gantt_chart_' . $validated_input['item_id'])) {
        return $validated_input;
      }
    }

  }

  /**
  * enqueue scripts and render the button to trigger data fetching and chart rendering
  **/
  public static function execute($atts, $content, $tag) {
    //error_reporting(E_ALL);

    $a = shortcode_atts( array(
  		'item_id' => 0,
      'date_start' => null,
      'date_end' => null,
      'scrollbar_x_start' => null,
      'scrollbar_x_end' => null,
      'event' => 'click'
  	), $atts );

    $validated_input = self::validate_input($a);

    if($validated_input) {

      wp_enqueue_style('dashicons');
      wp_enqueue_style('cb_bookings_chart_css', CB_ITEM_USAGE_RESTRICTION_ASSETS_URL . 'style/cb-bookings-gantt-chart.css');

      wp_enqueue_script( 'cb_bookings_amcharts_core_js', CB_ITEM_USAGE_RESTRICTION_ASSETS_URL . 'js/vendor/amcharts@4.9.19/core.js' );
      wp_enqueue_script( 'cb_bookings_amcharts_charts_js', CB_ITEM_USAGE_RESTRICTION_ASSETS_URL . 'js/vendor/amcharts@4.9.19/charts.js' );

      wp_enqueue_script( 'cb_bookings_gantt_chart_js', CB_ITEM_USAGE_RESTRICTION_ASSETS_URL . 'js/cb-booking-gantt-chart.js' );

      $nonce = self::create_item_chart_nonce($validated_input['item_id']);

      return '<button on' . $validated_input['event'] . '="init_cb_bookings_gantt_chart(this)"' .
              ' data-url="' . get_site_url(null, '', null) . '/wp-admin/admin-ajax.php' . '"' .
              ' data-nonce="' . $nonce . '"' .
              ' data-item_id="' . $validated_input['item_id'] . '"' .
              ' data-date_start="' . $validated_input['date_start']->format('Y-m-d') . '"' .
              ' data-date_end="' . $validated_input['date_end']->format('Y-m-d') . '"' .
              ' data-scrollbar_x_start="' . $validated_input['scrollbar_x_start'] . '"' .
              ' data-scrollbar_x_end="' . $validated_input['scrollbar_x_end'] . '"' .
              ' data-uuid="' . uniqid() . '"' .
              ' class="cb-booking-gantt-chart-button button action"><span style="padding-top: 4px;" class="dashicons dashicons-chart-bar"></span></button>'; //dashicons-list-view
    }

  }

  public static function create_item_chart_nonce($item_id) {
    return wp_create_nonce('cb_bookings_gantt_chart_' . $item_id);
  }

  public static function create_item_chart_nonces($item_ids) {
    $nonces = [];
    foreach ($item_ids as $item_id) {
      $nonces[$item_id] = self::create_item_chart_nonce($item_id);
    }

    return $nonces;
  }

  private static function is_blocking_booking($booking, $blocking_user_id) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
          return $booking->user_id == $blocking_user_id;
        break;
      case 2:
          return $booking->post_type == \CommonsBooking\Wordpress\CustomPostType\Restriction::getPostType();
        break;
    }
  }

  public static function group_bookings($bookings, $blocking_user_id) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        $status = 'status';
        $user_id = 'user_id';
        break;
      case 2:
        $status = 'post_status';
        $user_id = 'post_author';
        break;
    }

    $grouped_bookings = [
      'location' => [],
      'blocking' => [],
      'confirmed' => [],
      'aborted' => [],
      'overbooking' => [],
      'blocked' => [],
      'canceled' => []
    ];

    foreach ($bookings as $booking) {
      if(self::is_blocking_booking($booking, $blocking_user_id)) {
        $grouped_bookings['blocking'][] = $booking;
      }
      else {
        if($booking->$status == 'confirmed') {
          $grouped_bookings['confirmed'][] = $booking;
        }

        if($booking->$status == 'canceled') {

          if(CB_Item_Usage_Restriction_Booking::is_booking_canceled_after_start($booking)) {
            $grouped_bookings['aborted'][] = $booking;
          }
          else {
            $grouped_bookings['canceled'][] = $booking;
          }
        }

        if($booking->$status == 'blocked') {
          $grouped_bookings['blocked'][] = $booking;
        }
      }
    }

    return $grouped_bookings;
  }

  /**
  * return the booking data
  **/
  public static function get_bookings_data() {
    //error_reporting(E_ALL);
    //ini_set( 'display_errors', 1 );

    $validated_input = self::validate_input_with_nonce($_POST);

    if($validated_input) {
      $blocking_user_id = get_option('cb_item_restriction_blocking_user_id', null);
      $bookings = CB_Item_Usage_Restriction_Admin::fetch_bookings_in_period($validated_input['date_start']->format('Y-m-d'), $validated_input['date_end']->format('Y-m-d'), $validated_input['item_id']);

      if(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION == 2) {
        $restrictions = CB2_Restriction_Service::fetch_restrictions_in_period($validated_input['date_start']->format('Y-m-d'), $validated_input['date_end']->format('Y-m-d'), $validated_input['item_id']);
        $bookings = array_merge($bookings, $restrictions);
      }
      
      //prepare chart data from bookings
      $grouped_bookings = self::group_bookings($bookings, $blocking_user_id);

      if(count($grouped_bookings['blocking']) > 0) {
        foreach ($grouped_bookings['confirmed'] as $key => $booking) {
          if($booking->usage_during_restriction) {
            $grouped_bookings['overbooking'][] = $booking;
            unset($grouped_bookings['confirmed'][$key]);
          }
        }
      }

      $bookings_data = [];

      foreach ($grouped_bookings as $booking_type => $bookings) {
        $get_restriction = $booking_type == 'blocking' ? true : false;
        $bookings_data[$booking_type] = self::prepare_bookings_data($bookings, $booking_type, $get_restriction);
      }

      //separate bookings of types which could overlap
      $bookings_data = array_merge($bookings_data, self::separate_overlapping_bookings($bookings_data, 'canceled'));
      unset($bookings_data['canceled']);
      $bookings_data = array_merge($bookings_data, self::separate_overlapping_bookings($bookings_data, 'aborted'));
      unset($bookings_data['aborted']);

      //restore order
      $ordered_bookings_data = [];
      foreach ($grouped_bookings as $booking_type => $value) {
        foreach ($bookings_data as $key => $bookings) {
          if(strpos($key, $booking_type) !== false) {
            $ordered_bookings_data[$key] = $bookings;
          }
        }
      }

      //location days
      $location_days = CB_Bookings_GC_Location_Helper::get_location_days($validated_input['item_id'], $validated_input['date_start']->format('Y-m-d'), $validated_input['date_end']->format('Y-m-d'));
      $ordered_bookings_data['location'] = self::prepare_locations_data($location_days);

      $chart_data = [
        'bookings' => $ordered_bookings_data,
        'ticks' => [
          'min' => $validated_input['date_start']->format('Y-m-d') . ' 00:00:00',
          'max' => $validated_input['date_end']->format('Y-m-d') . ' 23:59:59'
        ],
        'scrollbar' => [
          'x' => [
            'start' => $validated_input['scrollbar_x_start'],
            'end' => $validated_input['scrollbar_x_end']
          ]
        ],
        'item' => [
          'name' => get_the_title($validated_input['item_id'])
        ]
      ];

      wp_send_json($chart_data);

    }
    else {
      wp_send_json_error([], 403);
    };

  }

  private static function separate_overlapping_bookings($bookings_data, $booking_type) {
    $booking_groups = [];

    $bookings = $bookings_data[$booking_type];

    //create start/end timestamps for detection of intersecting bookings
    foreach ($bookings as $key => $booking) {
      $bookings[$key]['timestamp_start'] = strtotime($booking['date_start']);
      $bookings[$key]['timestamp_end'] = strtotime($booking['date_end']);
    }

    //spread bookings over multiple groups to prevent intersections
    foreach ($bookings as $booking) {
      $booking_inserted = false;

      if(count($booking_groups) == 0) {
        $booking_groups[$booking_type . '_' . count($booking_groups)] = [$booking];
      }
      else {
        foreach ($booking_groups as $booking_group_name => $booking_group) {
          $intersections_in_group = false;

          foreach ($booking_group as $inserted_booking) {
            if(self::are_bookings_intersecting($booking, $inserted_booking)) {
              $intersections_in_group = true;
              break;
            }

          }

          if(!$intersections_in_group) {
            $booking_groups[$booking_group_name][] = $booking;
            $booking_inserted = true;
            break;
          }
        }

        if(!$booking_inserted) {
          $booking_groups[$booking_type . '_' . count($booking_groups)] = [$booking];
        }
      }
    }

    //remove timestamps
    foreach ($booking_groups as $booking_group_name => $booking_group) {
      foreach ($booking_group as $key => $inserted_booking) {
        unset($booking_groups[$booking_group_name][$key]['timestamp_start']);
        unset($booking_groups[$booking_group_name][$key]['timestamp_end']);
      }
    }

    return $booking_groups;
  }

  private static function are_bookings_intersecting($booking1, $booking2) {
    $no_intersection = $booking1['timestamp_start'] > $booking2['timestamp_end'] || $booking1['timestamp_end'] < $booking2['timestamp_start'];

    return !$no_intersection;
  }

  private static function prepare_locations_data($location_days) {
    if(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION == 1) {
      $cb_data = new CB_Data();
    }

    $locations = [];
    $locations_data = [];

    $roles = [
      CB_Bookings_GC_Location_Helper::ITEM_AVAILABLE => 'open',
      CB_Bookings_GC_Location_Helper::LOCATION_CLOSED => 'closed',
      CB_Bookings_GC_Location_Helper::OUT_OF_TIMEFRAME => 'none',
    ];

    foreach($location_days as $date => $day) {
      if(isset($day['location_id']) && !isset($locations[$day['location_id']])) {
        switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
          case 1:
            $locations[$day['location_id']] = $cb_data->get_location($day['location_id']);
            break;
          case 2:
            $locations[$day['location_id']] = CB2_Restriction_Service::get_location_array($day['location_id']);
            break;
        }
      }

      $locations_data[] = [
        'id' => 'Standort-ID',
        'date_start' => $date . ' 00:00:00',
        'date_end' => $date . ' 23:59:59',
        'type' => 'location',
        'comment' => 'a comment',
        'user' => [
          'name' => isset($day['location_id']) ? $locations[$day['location_id']]['name'] : '',
          'role' => $roles[$day['status']]
        ]
      ];
    }

    return $locations_data;
  }

  private static function prepare_bookings_data($bookings = [], $booking_type, $get_restriction = false) {
    //error_reporting(E_ALL);
    $bookings_data = [];

    foreach($bookings as $booking) {
      //var_dump($booking);
      switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
        case 1:
          $user = get_user_by('id', $booking->user_id);
          $booking_date_start = $booking->date_start;
          $booking_date_end = $booking->date_end;
          break;
        case 2:
          $user = get_user_by('id', $booking->post_author);
          $date_start_property = $booking->post_type == \CommonsBooking\Wordpress\CustomPostType\Restriction::getPostType() ? \CommonsBooking\Model\Restriction::META_START : \CommonsBooking\Model\Timeframe::REPETITION_START;
          $date_end_property = $booking->post_type == \CommonsBooking\Wordpress\CustomPostType\Restriction::getPostType() ? \CommonsBooking\Model\Restriction::META_END : \CommonsBooking\Model\Timeframe::REPETITION_END;
          $booking_date_start = date('Y-m-d', get_post_meta($booking->ID, $date_start_property, true));
          $booking_date_end = date('Y-m-d', get_post_meta($booking->ID, $date_end_property, true));
      }

      switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
        case 1:
          $item_id = $booking->item_id;
          $booking_id = $booking->id;
          break;
        case 2:
          $item_id = get_post_meta($booking->ID, \CommonsBooking\Model\Timeframe::META_ITEM_ID, true);
          $booking_id = $booking->ID;
      }

      if($get_restriction) {
        $restriction = CB_Item_Usage_Restriction::get_item_restriction_by_blocking_booking_id($item_id, $booking_id);

        if(isset($restriction)) {
          $restriction_hint = $restriction['restriction_hint'];
        }
      }

      $bookings_data[] = [
        'id' => (string) $booking_id,
        'date_start' => $booking_date_start . ' 00:00:00',
        'date_end' => $booking_date_end . ' 23:59:59',
        'type' => $booking_type,
        'comment' => isset($restriction_hint) ? $restriction_hint : $booking->comment,
        'user' => [
          'name' => !empty($user) ? $user->first_name . ' ' . substr($user->last_name, 0, 1) . '.' : 'unbekannt',
          'role' => !empty($user) ? $user->roles[0] : 'unbekannt'
        ]
      ];
    }

    return $bookings_data;
  }

}

?>
