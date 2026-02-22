<?php

use \CommonsBooking\Model\Booking;

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
    error_reporting(E_ALL);
    $restrictions_by_items = [];

    //load datetime of last check
    $datetime_start = get_option('cb_item_restriction_booking_check_datetime', null);

    if(!$datetime_start) {
      $datetime_start = new DateTime('1970-01-01');
    }

    //load all bookings that end between last check and yesterdays date
    $datetime_end = new DateTime();
    $datetime_end->modify('-1 day');
    $bookings = self::fetch_bookings_by_end_date($datetime_start->format('Y-m-d'), $datetime_end->format('Y-m-d'));

    //var_dump('booking count: ' . count($bookings));

    foreach ($bookings as $booking) {
      //check if booking doesn't belong to blocking user && status is confirmed or canceled on first booking day
      $no_blocking_user_booking = self::is_no_blocking_user_booking($booking);

      if($no_blocking_user_booking) {

        $has_booking_to_be_blocked = self::has_booking_to_be_blocked($booking);

        if($has_booking_to_be_blocked) {
          $item_id = self::get_booking_item_id($booking);

          //trigger_error('$item_id: ' . $item_id);

          //get restrictions of item
          if(!isset($restrictions_by_items[$item_id])) {
            $restrictions_by_items[$item_id] = CB_Item_Usage_Restriction::get_item_restrictions($item_id, 'desc');
          }

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

    $cancellation_time_str = self::get_booking_cancellation_time($booking);
    $cancellation_timestamp = $cancellation_time_str ? strtotime($cancellation_time_str) : null;
    $booking_status = self::get_booking_status($booking);
    $booking_date_start_str = self::get_booking_start_date_string($booking);

    if($booking_status == 'canceled' && $cancellation_timestamp) {
      $cancellation_time = new DateTime();
      $cancellation_time->setTimestamp($cancellation_timestamp);
      $booking_date_start = DateTime::createFromFormat('Y-m-d', $booking_date_start_str);
      $booking_date_start->setTime(0, 0, 0);

      //error_log('booking: ' . self::get_booking_id($booking) . ': ' . $cancellation_time->format('Y-m-d H:i:s') . ' / ' . $booking_date_start->format('Y-m-d H:i:s'));
      return $cancellation_time > $booking_date_start ? true : false;
    }
    else {
      return false;
    }
  }

  /**
   * Check if booking should be blocked
   * 
   * A booking should be blocked if it is confirmed without usage during restriction,
   * or if it was canceled after its start date.
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return bool
   */
  static function has_booking_to_be_blocked($booking) {

    $booking_canceled_after_start = self::is_booking_canceled_after_start($booking);
    $booking_status = self::get_booking_status($booking);
    $booking_usage_during_restriction = self::has_booking_usage_during_restriction($booking);

    return ($booking_status == 'confirmed' && (!$booking_usage_during_restriction)) || $booking_canceled_after_start;
  }

  static function block_booking($booking, $restriction, $revert = false, $inside_restriction = true) {
    $booking_date_start_str = self::get_booking_start_date_string($booking);
    $booking_date_end_str = self::get_booking_end_date_string($booking);
    
    $booking_date_start = DateTime::createFromFormat('Y-m-d', $booking_date_start_str);
    $booking_date_start->setTime(8, 0, 0);
    $booking_date_end = DateTime::createFromFormat('Y-m-d', $booking_date_end_str);
    $booking_date_end->setTime(20, 0, 0);

    //check if booking is completely inside the duration marked by $check_date_start & $check_date_end
    $restriction_date_start = DateTime::createFromFormat('Y-m-d', $restriction['date_start']);
    $restriction_date_start->setTime(0, 0, 0);
    $restriction_date_end = DateTime::createFromFormat('Y-m-d', $restriction['date_end']);
    $restriction_date_end->setTime(23, 59, 59);

    $cancellation_time_str = self::get_booking_cancellation_time($booking);
    $cancellation_timestamp = $cancellation_time_str ? strtotime($cancellation_time_str) : null;

    if($revert) {
      $status = $cancellation_timestamp ? 'canceled' : 'confirmed';
    }
    else {
      $status = self::get_blocked_status();
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
          $booking_contract = self::get_booking_contract($booking);
          if($booking_contract) {
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
          $booking_contract = self::get_booking_contract($booking);
          if($booking_contract) {
            $set_status = false;
          }
        }
      }

    }

    if($set_status) {
      //set booking status = blocked
      self::update_booking_status(self::get_booking_id($booking), $status);
    }
  }

  static function fetch_bookings_by_end_date($date_end_min, $date_end_max) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
          return self::fetch_cb1_bookings_by_end_date($date_end_min, $date_end_max);
        break;
      case 2:
          return self::fetch_cb2_bookings_by_end_date($date_end_min, $date_end_max);
        break;
    }
  }

  static function fetch_cb1_bookings_by_end_date($date_end_min, $date_end_max) {
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

  static function fetch_cb2_bookings_by_end_date($date_end_min, $date_end_max) {
		$date_end_min_timestamp = strtotime($date_end_min);
		$date_end_max_timestamp = strtotime($date_end_max) + 24 * 60 * 60 - 1;
	
		$args = [
		  'post_type' => \CommonsBooking\Wordpress\CustomPostType\Booking::getPostType(),
		  'posts_per_page' => -1,
		  'meta_query'  => [
		    'relation' => 'AND',
        [
          'key'     => \CommonsBooking\Model\Timeframe::REPETITION_END,
          'value'   => [$date_end_min_timestamp, $date_end_max_timestamp],
          'compare' => 'BETWEEN',
          'type'    => 'NUMERIC'
        ]
      ]
		];
		
		$query = new \WP_Query( $args );
		if ( $query->have_posts() ) {
		  return $query->get_posts();
		}
		else {
		  return [];
		}
	}

  static function update_booking_status($booking_id, $status) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        global $wpdb;
        $table_name = $wpdb->prefix . 'cb_bookings';
        $wpdb->update($table_name, array( 'status' => $status), array( 'id' => $booking_id));
        break;
      case 2:
        // For CB2, update the WordPress post_status
        wp_update_post([
          'ID' => $booking_id,
          'post_status' => $status
        ]);
        break;
    }
    //trigger_error('blocked booking: ' . $booking_id);
  }

  /**
   * Check if booking does not belong to blocking user
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return bool
   */
  static function is_no_blocking_user_booking($booking) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return $booking->user_id != get_option('cb_item_restriction_blocking_user_id');
      case 2:
        return true;
      default:
        return true;
    }
  }

  /**
   * Get booking item ID
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return int
   */
  static function get_booking_item_id($booking) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return $booking->item_id;
      case 2:
        return get_post_meta($booking->ID, Booking::META_ITEM_ID, true);
      default:
        return 0;
    }
  }

  /**
   * Get booking start date as string (Y-m-d format)
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return string
   */
  static function get_booking_start_date_string($booking) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return $booking->date_start;
      case 2:
        $start_timestamp = get_post_meta($booking->ID, Booking::REPETITION_START, true);
        return date('Y-m-d', $start_timestamp);
      default:
        return '';
    }
  }

  /**
   * Get booking end date as string (Y-m-d format)
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return string
   */
  static function get_booking_end_date_string($booking) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return $booking->date_end;
      case 2:
        $end_timestamp = get_post_meta($booking->ID, Booking::REPETITION_END, true);
        return date('Y-m-d', $end_timestamp);
      default:
        return '';
    }
  }

  /**
   * Get booking status
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return string
   */
  static function get_booking_status($booking) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return $booking->status;
      case 2:
        // In CB2, status is the standard WordPress post_status
        return $booking->post_status;
      default:
        return '';
    }
  }

  /**
   * Get booking cancellation time
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return string|null
   */
  static function get_booking_cancellation_time($booking) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return isset($booking->cancellation_time) ? $booking->cancellation_time : null;
      case 2:
        // In CB2, cancellation timestamp is stored in post meta
        $cancellation_date = get_post_meta($booking->ID, 'cancellation_date', true);
        return $cancellation_date ? date('Y-m-d H:i:s', $cancellation_date) : null;
      default:
        return null;
    }
  }

  /**
   * Check if booking has usage_during_restriction property
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return bool
   */
  static function has_booking_usage_during_restriction($booking) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return isset($booking->usage_during_restriction) && $booking->usage_during_restriction;
      case 2:
        // For CB2, check if usage_during_restriction meta exists and is true
        $usage = get_post_meta($booking->ID, 'usage_during_restriction', true);
        return $usage === '1' || $usage === 1 || $usage === true;
      default:
        return false;
    }
  }

  /**
   * Get booking contract property
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return mixed
   */
  static function get_booking_contract($booking) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return isset($booking->contract) ? $booking->contract : null;
      case 2:
        return get_post_meta($booking->ID, 'contract', true);
      default:
        return null;
    }
  }

  /**
   * Get booking ID
   * 
   * @param object $booking - CB1 booking object or CB2 WP_Post object
   * @return int
   */
  static function get_booking_id($booking) {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return $booking->id;
      case 2:
        return $booking->ID;
      default:
        return 0;
    }
  }

  /**
   * Get the appropriate blocked status for the current plugin version
   * 
   * CB1 supports 'blocked' status, CB2 uses 'canceled' without cancellation_time
   * 
   * @return string
   */
  static function get_blocked_status() {
    switch(CB_Item_Usage_Restriction::CB_PLUGIN_VERSION) {
      case 1:
        return 'blocked';
      case 2:
        return 'canceled';
      default:
        return 'blocked';
    }
  }
}

?>
