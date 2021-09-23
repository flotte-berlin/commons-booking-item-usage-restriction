<?php

class CB_Item_Usage_Restriction_Reminder {

  static function activate() {
    $datetime = new DateTime();
    $datetime->setTime(4, 0, 0, 0);
    $timestamp = $datetime->getTimestamp();
    wp_schedule_event( $timestamp, 'daily', 'cb_item_usage_restriction_reminder');
  }

  static function deactivate() {

    wp_clear_scheduled_hook('cb_item_usage_restriction_reminder');
  }

  static function check_ending_restrictions() {
    error_reporting(E_ALL);

    //settings
    $days_in_advance = get_option('cb_item_restriction_remind_days_in_advance', 2);
    $active_for_restriction_type = [
      1 => get_option('cb_item_restriction_remind_for_total_breakdown', false),
      2 => get_option('cb_item_restriction_remind_for_limited_usage', false)
    ];

    //calculate reference date for reminder
    $diff = new DateInterval('P'. $days_in_advance .'D');
    $date_end = new DateTime();
    $date_end->add($diff);
    $date_end_str = $date_end->format('Y-m-d');

    //get all cb_items
    $cb_items = self::get_all_cb_items();

    $cb_item_usage_restriction_admin = new CB_Item_Usage_Restriction_Admin();
    $cb_item_usage_restriction_admin->load_settings();

    foreach ($cb_items as $cb_item) {
      //load restrictions of item
      $restrictions = CB_Item_Usage_Restriction::get_item_restrictions($cb_item->ID);
      
      foreach($restrictions as $restriction) {
        //check restriction type
        if(active_for_restriction_type[$restriction['restriction_type']]) {
          //check end date against due date
          if($restriction['date_end'] == $date_end_str) {
            //find coordinator(s)
            $coordinators = $cb_item_usage_restriction_admin->get_coordinators($cb_item->ID);
            //send reminder email to coordinators
            $cb_item_usage_restriction_admin->send_mail_by_reason_to_recipients($coordinators, $cb_item->ID, $restriction['restriction_type'], 'remind_restriction_end', $restriction['date_start'], $restriction['date_end'], '', $cb_item_usage_restriction_admin->get_hint_history($restriction));
          }
        }
      }
    }
  }

  static function get_all_cb_items() {

    //get all items
    $item_posts_args = array(
      'numberposts' => -1,
      'post_type'   => 'cb_items',
      'orderby'    => 'post_title',
      'order' => 'ASC'
    );
    
    return get_posts( $item_posts_args );
  }
}

?>