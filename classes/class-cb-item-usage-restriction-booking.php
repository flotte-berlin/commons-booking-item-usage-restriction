<?php

class CB_Item_Usage_Restriction_Booking {

  static function activate() {
    $datetime = new DateTime();
    $datetime->setTime(1, 0, 0, 0);
    $timestamp = $datetime->getTimestamp();
    wp_schedule_event( $timestamp, 'daily', 'cb_item_usage_restriction_booking_check');
  }


  static function deactivate() {

    wp_clear_scheduled_hook('cb_item_usage_restriction_booking_check');
  }

  static function check_blocked_bookings() {
    //error_reporting(E_ALL);
    $restrictions_by_items = [];

    //load datetime of last check
    $datetime_start = get_option('cb_item_restriction_booking_check_datetime', null);

    if(!$datetime_start) {
      $datetime_start = new DateTime('1970-01-01');
    }

    $datetime_end = new DateTime();
    $datetime_end->modify('-1 day');

    //load all bookings that end between last check and yesterday
    $bookings = self::fetch_bookings_by_end_date($datetime_start->format('Y-m-d'), $datetime_end->format('Y-m-d'));

    //error_log('booking count: ' . count($bookings));

    foreach ($bookings as $booking) {
      //check if booking doesn't belong to blocking user && status is confirmed or canceled on first booking day
      if($booking->user_id != get_option('cb_item_restriction_blocking_user_id')) {

        $has_booking_to_be_blocked = self::has_booking_to_be_blocked($booking);

        if($has_booking_to_be_blocked) {
          $item_id = $booking->item_id;

          //trigger_error('$item_id: ' . $item_id);

          //get restrictions of item
          if(!isset($restrictions_by_items[$item_id])) {
            $restrictions_by_items[$item_id] = CB_Item_Usage_Restriction::get_item_restrictions($item_id, 'desc');
          }

          //trigger_error('restriction count: ' . count($restrictions_by_items[$item_id]) . ' for item ' . $item_id);

          foreach ($restrictions_by_items[$item_id] as $restriction) {

            //check if restriction type is total breakdown
            if($restriction['restriction_type'] == 1) {
              self::block_booking($booking, $restriction);
            }
          }
        }
      }
    }

    //store datetime of this check
    update_option( 'cb_item_restriction_booking_check_datetime', $datetime_end, false );
  }

  static function is_booking_canceled_after_start($booking) {

    $cancellation_timestamp = isset($booking->cancellation_time) ? strtotime($booking->cancellation_time) : null;

    if($booking->status == 'canceled' && $cancellation_timestamp) {
      $cancellation_time = new DateTime();
      $cancellation_time->setTimestamp($cancellation_timestamp);
      $booking_date_start = DateTime::createFromFormat('Y-m-d', $booking->date_start);
      $booking_date_start->setTime(0, 0, 0);

      //error_log('booking: ' . $booking->id . ': ' . $cancellation_time->format('Y-m-d H:i:s') . ' / ' . $booking_date_start->format('Y-m-d H:i:s'));
      return $cancellation_time > $booking_date_start ? true : false;
    }
    else {
      return false;
    }
  }

  static function has_booking_to_be_blocked($booking) {

    $booking_canceled_after_start = self::is_booking_canceled_after_start($booking);

    return ($booking->status == 'confirmed' && (!isset($booking->usage_during_restriction) || !$booking->usage_during_restriction)) || $booking_canceled_after_start;
  }

  static function block_booking($booking, $restriction, $revert = false, $inside_restriction = true) {
    $booking_date_start = DateTime::createFromFormat('Y-m-d', $booking->date_start);
    $booking_date_start->setTime(8, 0, 0);
    $booking_date_end = DateTime::createFromFormat('Y-m-d', $booking->date_end);
    $booking_date_end->setTime(20, 0, 0);

    //check if booking is completely inside the duration marked by $check_date_start & $check_date_end
    $restriction_date_start = DateTime::createFromFormat('Y-m-d', $restriction['date_start']);
    $restriction_date_start->setTime(0, 0, 0);
    $restriction_date_end = DateTime::createFromFormat('Y-m-d', $restriction['date_end']);
    $restriction_date_end->setTime(23, 59, 59);

    $cancellation_timestamp = isset($booking->cancellation_time) ? strtotime($booking->cancellation_time) : null;

    if($revert) {
      $status = $cancellation_timestamp ? 'canceled' : 'confirmed';
    }
    else {
      $status = 'blocked';
    }

    $set_status = false;
    $completely_inside_booking = $booking_date_start >= $restriction_date_start && $booking_date_end <= $restriction_date_end;
    $booking_condition = $inside_restriction ? $completely_inside_booking : !$completely_inside_booking;

    if(!$revert) {
      //booking is completely inside reference period
      if($booking_condition) {

        $set_status = true;
        //if contract-extension plugin is installed
        if(cb_item_usage_restriction\is_plugin_active('commons-booking-contract-extension.php')) {
          //if booking has contract: don't set status
          if($booking->contract) {
            $set_status = false;
          }
        }

      }
    }
    else {
      //booking is completely outside reference period
      if($booking_condition) {
        $set_status = true;

        //if contract-extension plugin is installed
        if(cb_item_usage_restriction\is_plugin_active('commons-booking-contract-extension.php')) {
          //if booking has contract: don't set status
          if($booking->contract) {
            $set_status = false;
          }
        }
      }

    }

    if($set_status) {
      //set booking status = blocked
      self::update_booking_status($booking->id, $status);
    }
  }

  static function fetch_bookings_by_end_date($date_end_min, $date_end_max) {
    global $wpdb;

    //trigger_error('$date_end_min: ' . $date_end_min);
    //trigger_error('$date_end_max: ' . $date_end_max);

    //get bookings data
    $table_name = $wpdb->prefix . 'cb_bookings';
    $select_statement = "SELECT * FROM $table_name WHERE ".
                        "date_end BETWEEN '".$date_end_min."' ".
                        "AND '".$date_end_max."' ";

    $bookings_result = $wpdb->get_results($select_statement);

    return $bookings_result;
  }

  static function update_booking_status($booking_id, $status) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cb_bookings';
    $wpdb->update($table_name, array( 'status' => $status), array( 'id' => $booking_id));
    //trigger_error('blocked booking: ' . $booking_id);
  }
}

?>
