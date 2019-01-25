<?php

class CB_Item_Usage_Restriction {

  const META_KEY = 'cb_item_usage_restrictions';

  /**
  * adds restriction entry to meta data of the given item (post)
  **/
  static public function add_item_restriction($restriction_data, $informed_users = array(), $additional_email_recipients = array(), $responsible_users = array()) {

    $item_restrictions = self::get_item_restrictions($restriction_data['item_id']);

    $current_user = wp_get_current_user();
    $restriction_data['created_by_user_id'] = $current_user->ID;
    $restriction_data['created_at'] = new DateTime();

    $restriction_data['informed_user_ids'] = array();
    foreach ($informed_users as $user) {
      array_push($restriction_data['informed_user_ids'], $user->ID);
    }

    $restriction_data['additional_email_recipients'] = $additional_email_recipients;

    $restriction_data['responsible_user_ids'] = array();
    foreach ($responsible_users as $user) {
      array_push($restriction_data['responsible_user_ids'], $user->ID);
    }

    array_push($item_restrictions, $restriction_data);

    self::save_restrictions($restriction_data['item_id'], $item_restrictions);

  }

  static private function save_restrictions($item_id, $item_restrictions) {
    $add_result = add_post_meta( $item_id, self::META_KEY, $item_restrictions, true );

    if ( ! $add_result ) {
       $update_result = update_post_meta( $item_id, self::META_KEY, $item_restrictions );
    }
  }

  /**
  * fetches existing restrictions
  **/
  static public function get_item_restrictions($item_id, $order = null) {
    $item_restrictions = get_metadata('post', $item_id, self::META_KEY, true);

    if($item_restrictions) {

      if($order == 'asc' || $order == 'desc') {
        usort($item_restrictions, function ($item1, $item2) use ($order) {
            if ($item1['date_start_valid'] == $item2['date_start_valid']) return 0;
            return $item1['date_start_valid'] > $item2['date_start_valid'] ? $order == 'asc' ? -1 : 1 : $order == 'desc' ? 1 : -1;
        });
      }

    }
    else {
      $item_restrictions = array();
    }

    return $item_restrictions;
  }

  static public function remove_item_restriction($item_id, $item_restrictions, $index) {

    array_splice($item_restrictions, $index, 1);

    self::save_restrictions($item_id, $item_restrictions);
  }

  /**
  * add restriction hint to content of Commons Booking items, if there is a current one or in the near future
  **/
  public function render_current_restrictions($content) {
    $post = $GLOBALS['post'];

    //for cb items add restrictions to content
    if ($post->post_type == 'cb_items') {
      $item_restrictions = self::get_item_restrictions($post->ID, 'asc');
      $restrictions = array();

      $current_date = new DateTime();
      $current_date->setTime( 0, 0, 0 );
      $current_date_timestamp = $current_date->getTimestamp();

      $cb_settings = new CB_Admin_Settings();
      $days_to_show = $cb_settings->get_settings( 'bookings', 'bookingsettings_daystoshow' );
      $booking_period = 86400 * (integer) $days_to_show;
      $booking_period_end_timestamp = $current_date_timestamp + $booking_period;

      foreach ($item_restrictions as $restriction) {
        $date_start_valid_timestamp = $restriction['date_start_valid']->getTimestamp();
        $date_end_valid_timestamp = $restriction['date_end_valid']->getTimestamp();

        if($date_end_valid_timestamp >= $current_date_timestamp && $date_start_valid_timestamp <= $booking_period_end_timestamp) {
          array_push($restrictions, $restriction);
        }
      }

      $appears_always = get_option('cb_item_restriction_appears_always_in_article_description', false);

      ob_start();
      include( CB_ITEM_USAGE_RESTRICTION_PATH . 'templates/show-restriction-template.php');
      $buffer = ob_get_clean();
      return $content .= $buffer;

    }
    // otherwise returns the database content
    return $content;
  }
}

?>
