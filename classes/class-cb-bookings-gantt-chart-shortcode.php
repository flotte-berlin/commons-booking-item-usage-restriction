<?php

class CB_Bookings_Gantt_Chart_Shortcode {

  private static function validate_input($input) {

    $item_id = (int) $input['item_id'];
    $date_start = isset($input['date_start']) && strlen($input['date_start']) > 0 ? new DateTime($input['date_start']) : null;
    $date_end = isset($input['date_end']) && strlen($input['date_end']) > 0 ? new DateTime($input['date_end']) : null;

    if($item_id && $date_start && $date_end) {

      if($date_start <= $date_end) {

        if(get_post_type($item_id) == 'cb_items') {
          //$item = $items[0];

          return [
            'item_id' => $item_id,
            'date_start' => $date_start,
            'date_end' => $date_end
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
      'date_end' => null
  	), $atts );

    $validated_input = self::validate_input($a);

    if($validated_input) {

      wp_enqueue_style('dashicons');

      wp_enqueue_script( 'cb_bookings_chart_moment_js', 'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.1.0/moment.min.js' );
      wp_enqueue_script( 'cb_bookings_chart_js', 'https://cdn.jsdelivr.net/npm/chart.js@2.9.3' );

      wp_enqueue_script( 'cb_bookings_gantt_chart_js', CB_ITEM_USAGE_RESTRICTION_ASSETS_URL . 'js/cb-booking-gantt-chart.js' );

      return '<button onclick="create_cb_bookings_gantt_chart(this)"' .
              ' data-url="' . get_site_url(null, '', null) . '/wp-admin/admin-ajax.php' . '"' .
              ' data-item_id="' . $validated_input['item_id'] . '"' .
              ' data-date_start="' . $validated_input['date_start']->format('Y-m-d') . '"' .
              ' data-date_end="' . $validated_input['date_end']->format('Y-m-d') . '"' .
              ' class="button action" style="padding-top: 3px;"><span class="dashicons dashicons-editor-aligncenter"></span></button>'; //dashicons-list-view
    }

  }

  /**
  * return the booking data
  **/
  public static function get_bookings_data() {

    $validated_input = self::validate_input($_POST);

    if($validated_input) {
      $cb_admin_booking_admin = new CB_Admin_Booking_Admin();
      $bookings = $cb_admin_booking_admin->fetch_bookings_in_period($validated_input['date_start']->format('Y-m-d'), $validated_input['date_end']->format('Y-m-d'), $validated_input['item_id']);

      $blocking_user_id = get_option('cb_item_restriction_blocking_user_id', null);

      //prepare chart data from bookings
      $grouped_bookings = [
        'blocking' => [],
        'user' => [],
        'overbooking' => []
      ];

      foreach ($bookings as $booking) {
        if($booking->user_id == $blocking_user_id) {
          $grouped_bookings['blocking'][] = $booking;
        }
        else {
          $grouped_bookings['user'][] = $booking;
        }
      }

      if(count($grouped_bookings['blocking']) > 0) {
        foreach ($grouped_bookings['user'] as $key => $booking) {
          $blocking_booking_time = new DateTime($grouped_bookings['blocking'][0]->booking_time);
          $booking_time = new DateTime($booking->booking_time);

          if($booking_time >= $blocking_booking_time) {
            $grouped_bookings['overbooking'][] = $booking;
            unset($grouped_bookings['user'][$key]);
          }
        }
      }

      $bookings_data = [
        'blocking' => self::prepare_bookings_data($grouped_bookings['blocking']),
        'user' => self::prepare_bookings_data($grouped_bookings['user']),
        'overbooking' => self::prepare_bookings_data($grouped_bookings['overbooking'])
      ];

      $chart_data = [
        'bookings' => $bookings_data,
        'ticks' => [
          'min' => $validated_input['date_start']->format('Y-m-d') . ' 00:00:00',
          'max' => $validated_input['date_end']->format('Y-m-d') . ' 23:59:59'
        ]
      ];

      wp_send_json($chart_data);

    }
    else {
      wp_send_json_error([], 403);
    };

  }

  private static function prepare_bookings_data($bookings) {
    $bookings_data = [];

    foreach ($bookings as $booking) {
      $bookings_data[] = [
        'id' => $booking->id,
        'date_start' => $booking->date_start . ' 00:00:00',
        'date_end' => $booking->date_end . ' 23:59:59'
      ];
    }

    return $bookings_data;
  }
}

?>
