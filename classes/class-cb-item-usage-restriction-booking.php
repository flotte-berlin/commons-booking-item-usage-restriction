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

    //trigger_error('booking count: ' . count($bookings));

    foreach ($bookings as $booking) {

      //check if booking doesn't belong to blocking user && status is confirmed
      if($booking->user_id != get_option('cb_item_restriction_blocking_user_id') && $booking->status == 'confirmed') {
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
            $booking_date_start = DateTime::createFromFormat('Y-m-d', $booking->date_start);
            $booking_date_start->setTime(8, 0, 0);
            $booking_date_end = DateTime::createFromFormat('Y-m-d', $booking->date_end);
            $booking_date_end->setTime(20, 0, 0);

            //check if booking is completely inside restriction (date_start_valid & date_end_valid aren't used here because of a previous bug, that caused a time of 00:00:00 for date_end_valid)
            $restriction_date_start = DateTime::createFromFormat('Y-m-d', $restriction['date_start']);
            $restriction_date_start->setTime(0, 0, 0);
            $restriction_date_end = DateTime::createFromFormat('Y-m-d', $restriction['date_end']);
            $restriction_date_end->setTime(23, 59, 59);
            if($booking_date_start >= $restriction_date_start && $booking_date_end <= $restriction_date_end) {

              $set_blocked = true;
              //if contract-extension plugin is installed
              if(cb_item_usage_restriction\is_plugin_active('commons-booking-contract-extension.php')) {
                //if booking has contract: don't set 'blocked' status
                if($booking->contract) {
                  $set_blocked = false;
                }
              }

              if($set_blocked) {
                //set booking status = blocked
                self::update_booking_status($booking->id, 'blocked');
              }

            }
          }
        }

      }
    }

    //store datetime of this check
    update_option( 'cb_item_restriction_booking_check_datetime', $datetime_end, false );
  }

  static function fetch_bookings_by_end_date($date_end_min, $date_end_max) {
    global $wpdb;

    //trigger_error('$date_end_min: ' . $date_end_min);
    //trigger_error('$date_end_max: ' . $date_end_max);

    //get bookings data
    $table_name = $wpdb->prefix . 'cb_bookings';
    $select_statement = "SELECT * FROM $table_name WHERE ".
                        "date_start BETWEEN '".$date_end_min."' ".
                        "AND '".$date_end_max."' ".
                        "AND status = 'confirmed'";

    $bookings_result = $wpdb->get_results($select_statement);

    return $bookings_result;
  }

  static function update_booking_status($booking_id, $status) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cb_bookings';
    $wpdb->update($table_name, array( 'status' => 'blocked'), array( 'id' => $booking_id));
    //trigger_error('blocked booking: ' . $booking_id);
  }
}

?>
