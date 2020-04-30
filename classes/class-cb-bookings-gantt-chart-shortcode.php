<?php

class CB_Bookings_Gantt_Chart_Shortcode {

  private static function validate_input($input) {

    $item_id = (int) $input['item_id'];
    $date_start = isset($input['date_start']) && strlen($input['date_start']) > 0 ? new DateTime($input['date_start']) : null;
    $date_end = isset($input['date_end']) && strlen($input['date_end']) > 0 ? new DateTime($input['date_end']) : null;
    $event = isset($input['event']) && ($input['event'] == 'click' || $input['event'] == 'mouseover') ? $input['event'] : 'click';

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

        if(get_post_type($item_id) == 'cb_items') {
          //$item = $items[0];

          return [
            'item_id' => $item_id,
            'date_start' => $date_start,
            'date_end' => $date_end,
            'event' => $event
          ];
        }

      }
    }
  }

  /**
  * enqueue scripts and render the button to trigger data fetching and chart rendering
  **/
  public static function execute($atts, $content, $tag) {
    error_reporting(E_ALL);

    $a = shortcode_atts( array(
  		'item_id' => 0,
      'date_start' => null,
      'date_end' => null,
      'event' => 'click'
  	), $atts );

    $validated_input = self::validate_input($a);

    if($validated_input) {

      wp_enqueue_style('dashicons');
      wp_enqueue_style('cb_bookings_chart_css', CB_ITEM_USAGE_RESTRICTION_ASSETS_URL . 'style/cb-bookings-gantt-chart.css');

      wp_enqueue_script( 'cb_bookings_chart_moment_js', CB_ITEM_USAGE_RESTRICTION_ASSETS_URL . 'js/vendor/moment.js@2.1.0/moment.min.js' );
      wp_enqueue_script( 'cb_bookings_chart_js', CB_ITEM_USAGE_RESTRICTION_ASSETS_URL . 'js/vendor/chart.js@2.9.3/chart.js' );

      wp_enqueue_script( 'cb_bookings_gantt_chart_js', CB_ITEM_USAGE_RESTRICTION_ASSETS_URL . 'js/cb-booking-gantt-chart.js' );

      return '<button on' . $validated_input['event'] . '="init_cb_bookings_gantt_chart(this)"' .
              ' data-url="' . get_site_url(null, '', null) . '/wp-admin/admin-ajax.php' . '"' .
              ' data-item_id="' . $validated_input['item_id'] . '"' .
              ' data-date_start="' . $validated_input['date_start']->format('Y-m-d') . '"' .
              ' data-date_end="' . $validated_input['date_end']->format('Y-m-d') . '"' .
              ' data-uuid="' . uniqid() . '"' .
              ' class="button action"><span style="padding-top: 4px;" class="dashicons dashicons-chart-bar"></span></button>'; //dashicons-list-view
    }

  }

  /**
  * return the booking data
  **/
  public static function get_bookings_data() {

    $validated_input = self::validate_input($_POST);

    if($validated_input) {
      $bookings = CB_Item_Usage_Restriction_Admin::fetch_bookings_in_period($validated_input['date_start']->format('Y-m-d'), $validated_input['date_end']->format('Y-m-d'), $validated_input['item_id']);

      $blocking_user_id = get_option('cb_item_restriction_blocking_user_id', null);

      //prepare chart data from bookings
      $grouped_bookings = [
        'blocking' => [],
        'confirmed' => [],
        'overbooking' => [],
        'blocked' => [],
        'canceled' => []
      ];

      foreach ($bookings as $booking) {
        if($booking->user_id == $blocking_user_id) {
          $grouped_bookings['blocking'][] = $booking;
        }
        else {
          if($booking->status == 'confirmed') {
            $grouped_bookings['confirmed'][] = $booking;
          }

          if($booking->status == 'canceled') {
            $grouped_bookings['canceled'][] = $booking;
          }

          if($booking->status == 'blocked') {
            $grouped_bookings['blocked'][] = $booking;
          }
        }
      }

      if(count($grouped_bookings['blocking']) > 0) {
        foreach ($grouped_bookings['confirmed'] as $key => $booking) {
          $blocking_booking_time = new DateTime($grouped_bookings['blocking'][0]->booking_time);
          $booking_time = new DateTime($booking->booking_time);

          if($booking->usage_during_restriction) {
            $grouped_bookings['overbooking'][] = $booking;
            unset($grouped_bookings['confirmed'][$key]);
          }
        }
      }

      $bookings_data = [
        'blocking' => self::prepare_bookings_data($grouped_bookings['blocking'], true),
        'confirmed' => self::prepare_bookings_data($grouped_bookings['confirmed']),
        'canceled' => self::prepare_bookings_data($grouped_bookings['canceled']),
        'blocked' => self::prepare_bookings_data($grouped_bookings['blocked']),
        'overbooking' => self::prepare_bookings_data($grouped_bookings['overbooking'])
      ];

      $chart_data = [
        'bookings' => $bookings_data,
        'ticks' => [
          'min' => $validated_input['date_start']->format('Y-m-d') . ' 00:00:00',
          'max' => $validated_input['date_end']->format('Y-m-d') . ' 23:59:59'
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

  private static function prepare_bookings_data($bookings = [], $get_restriction = false) {
    error_reporting(E_ALL);
    $bookings_data = [];

    foreach($bookings as $booking) {
      $user = get_user_by('id', $booking->user_id);

      if($get_restriction) {
        $restriction = CB_Item_Usage_Restriction::get_item_restriction_by_blocking_booking_id($booking->item_id, $booking->id);

        $restriction_hint = $restriction['restriction_hint'];
      }

      $bookings_data[] = [
        'id' => $booking->id,
        'date_start' => $booking->date_start . ' 00:00:00',
        'date_end' => $booking->date_end . ' 23:59:59',
        'comment' => isset($restriction_hint) ? $restriction_hint : $booking->comment,
        'user' => [
          'name' => $user->first_name . ' ' . substr($user->last_name, 0, 1) . '.',
          'role' => $user->roles[0]
        ]
      ];
    }

    return $bookings_data;
  }
}

?>
