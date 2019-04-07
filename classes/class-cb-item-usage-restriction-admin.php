<?php

class CB_Item_Usage_Restriction_Admin {

  const PAGE = 'cb_item_usage_restriction';

  private $cb_items;
  private $valid_cb_item_ids = array();
  private $blocking_user;
  private $email_message;
  private $consider_responsible_users;

  function add_plugin_admin_menu() {

    $capability = 'publish_pages'; // restrict access to whole menu to users with this capability

    add_submenu_page(
        'cb_timeframes', // parent_menu_slug
        item_usage_restriction\__( 'USAGE_RESTRICTION_PAGE_TITLE', 'commons-booking-item-usage-restriction', "Usage Restriction"), // page_title
        item_usage_restriction\__( 'USAGE_RESTRICTION_MENU_TITLE', 'commons-booking-item-usage-restriction', "Restriction"), // menu_title
        $capability, // capability
        self::PAGE, // menu_slug
        array($this, 'cb_item_usage_restriction_admin_page_handler') // handler method
        );
  }

  function cb_item_usage_restriction_admin_page_handler() {

    wp_enqueue_script( 'jquery-ui-dialog' );
    wp_enqueue_style( 'wp-jquery-ui-dialog' );

    //get settings
    $this->load_settings();

    $settings_correct = $this->blocking_user &&
        strlen($this->email_message['restriction_1']['subject']) > 0 && strlen($this->email_message['restriction_1']['body']) > 0 &&
        strlen($this->email_message['restriction_2']['subject']) > 0 && strlen($this->email_message['restriction_2']['body']) > 0 &&
        strlen($this->email_message['edit_restriction']['subject']) > 0 && strlen($this->email_message['edit_restriction']['body']) &&
        strlen($this->email_message['delete_restriction']['subject']) > 0 && strlen($this->email_message['delete_restriction']['body']) > 0 ? true : false;

    //incorrect settings lead to a warning (handled in template)
    if($settings_correct) {

      //get all items
      $item_posts_args = array(
        'numberposts' => -1,
        'post_type'   => 'cb_items',
        'orderby'    => 'post_date',
        'order' => 'ASC'
      );
      $this->cb_items = get_posts( $item_posts_args );

      foreach ($this->cb_items as $cb_item) {
        $this->valid_cb_item_ids[] = $cb_item->ID;
      }

      global $title;

      //provide items for create-restriction-template
      $cb_items = $this->cb_items;

      //set defaults for create-restriction-template
      $form_values = array(
        'item_id' => '',
        'date_start' => new DateTime(),
        'date_end' => new DateTime(),
        'restriction_hint' => '',
        'additional_emails' => '',
        'restriction_type' => 1
      );

      //params
      $restriction_list_cb_item_id =  isset($_GET['restriction_list_cb_item_id']) ? (integer) $_GET['restriction_list_cb_item_id'] : null;
      $page = self::PAGE;

      if(isset($_POST) && count($_POST) > 0) {

        switch($_POST['action']) {
          case 'edit-restriction':
            $this->edit_restriction();
          break;

          case 'delete-restriction':
            $this->delete_restriction();
          break;

          case 'create-restriction':
            $form_values = $this->create_restriction($form_values);
          break;
        }

      }

      if(isset($restriction_list_cb_item_id)) {
        $consider_responsible_users = $this->consider_responsible_users;
        $item_restrictions = CB_Item_Usage_Restriction::get_item_restrictions($restriction_list_cb_item_id, 'desc');
      }
    }

    include_once( CB_ITEM_USAGE_RESTRICTION_PATH . 'templates/admin-page-template.php' );
  }

  function edit_restriction() {
    $validation_result = $this->validate_edit_restriction_form_input();

    if(count($validation_result['errors']) == 0) {

      //var_dump($validation_result);

      $item_restriction = $this->find_item_usage_restriction($validation_result['data']['item_id'], $validation_result['data']['created_by_user_id'], $validation_result['data']['created_at_timestamp']);

      if(isset($item_restriction)) {

        //check if end date has changed
        if($validation_result['data']['date_end'] == $item_restriction['date_end']) {
          $message = item_usage_restriction\__('NO_DATE_END_CHANGE', 'commons-booking-item-usage-restriction', "The end date hasn't changed.");
          $class = 'notice notice-warning';
          echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
          return false;
        }

        //check if location (timeframe) exists - actually can only fail when a restriction ist prolonged
        $cb_booking = new CB_Booking();
        $location_id = $cb_booking->get_booking_location_id($item_restriction['date_start'], $validation_result['data']['date_end'], $validation_result['data']['item_id']);
        if(!$location_id) {
          $message = item_usage_restriction\__('NO_TIMEFRAME', 'commons-booking-item-usage-restriction', "There's no timeframe for the given period. You've to create one before you can set a restriction.");
          $class = 'notice notice-error';
          echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
          return false;
        }

        //check new end date (if it equals or is after start date of existing restriction)
        if($validation_result['data']['date_end_valid'] >= $item_restriction['date_start_valid']) {
          //prepare  timestamps for email checks
          $new_end_date_timestamp = $validation_result['data']['date_end_valid']->getTimestamp();
          $old_date_end = $item_restriction['date_end'];
          $old_end_date_timestamp = strtotime($old_date_end);

          $today_datetime = new DateTime();
          $today_datetime->setTime( 0, 0, 0 );
          $today_timestamp = $today_datetime->getTimestamp();

          // check overlapping with existing restriction of type 'total breakdown'
          if($new_end_date_timestamp > $old_end_date_timestamp) {
            $overlapping_check_start_date = $item_restriction['date_end_valid'];
            $overlapping_check_start_date->setTimestamp($overlapping_check_start_date->getTimestamp() + 1); //end dates have 23:59:59 as time, start dates 00:00:00
            $overlapping = $this-> check_restriction_1_overlapping($item_restriction['item_id'], $overlapping_check_start_date, $validation_result['data']['date_end_valid'], $item_restriction['restriction_type']);

            if($overlapping) {
              $message = item_usage_restriction\__('RESTRICTION_FOR_PERIOD_ALREADY_EXISTING', 'commons-booking-item-usage-restriction', "There's already a restriction of type 'total breakfown'for the given period.");
              $class = 'notice notice-error';
              echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
              return false;
            }
          }

          $bookings_for_update_email = array();

          //shortened restriction: if new end date < old end date & old end date >= today
          if($new_end_date_timestamp < $old_end_date_timestamp && $old_end_date_timestamp >= $today_timestamp) {
            // update email to users with bookings that have intersection with time between new end date (or today, if it's in the past) and old end date
            $date_start = $new_end_date_timestamp < $today_timestamp ? $today_datetime->format('Y-m-d') : $validation_result['data']['date_end'];
            $unfiltered_bookings = $this->fetch_current_and_future_bookings($date_start, $old_date_end, $item_restriction['item_id']);

            // delete email to users with booking that starts after new restriction end date
            $bookings_for_delete_email = array();
            foreach($unfiltered_bookings as $booking) {
              $booking_start_timestamp = strtotime($booking->date_start);
              if($booking_start_timestamp > $new_end_date_timestamp) {
                array_push($bookings_for_delete_email, $booking);
              }
              else {
                array_push($bookings_for_update_email, $booking);
              }
            }

            //send deleted emails
            $informed_users = array();

            foreach ($bookings_for_delete_email as $booking) {
              $user = get_user_by('id', $booking->user_id);
              if(!in_array($user, $informed_users)) {
                array_push($informed_users, $user);
              }
            }
            $email_recipients = $informed_users;

            $this->send_mail_by_reason_to_recipients($email_recipients, $item_restriction['item_id'], 'delete_restriction', $item_restriction['date_start'], $item_restriction['date_end'], $validation_result['data']['update_comment']);

          }

          //prolonged restriction: new end date > old end date & new end date >= today:
          if($new_end_date_timestamp > $old_end_date_timestamp && $new_end_date_timestamp >= $today_timestamp) {

            $date_start = $old_end_date_timestamp < $today_timestamp ? $today_datetime->format('Y-m-d') : $old_date_end;
            $unfiltered_bookings = $this->fetch_current_and_future_bookings($date_start, $validation_result['data']['date_end'], $item_restriction['item_id']);

            // update email to users with booking that has end date between old end date (or today, if it's in the past) and new restriction end date, but not start date
            // created restriction email to users with booking that starts between old (or today, if it's in the past) and new restriction end date
            $bookings_for_create_email = array();
            $date_start_timestamp = strtotime($date_start);
            foreach($unfiltered_bookings as $booking) {
              $booking_start_timestamp = strtotime($booking->date_start);
              if($booking_start_timestamp > $date_start_timestamp && $booking_start_timestamp <= $new_end_date_timestamp) {
                array_push($bookings_for_create_email, $booking);
              }
              else {
                array_push($bookings_for_update_email, $booking);
              }
            }

            //send created emails
            $informed_users = array();

            foreach ($bookings_for_create_email as $booking) {
              $user = get_user_by('id', $booking->user_id);
              if(!in_array($user, $informed_users)) {
                array_push($informed_users, $user);
              }
            }
            $email_recipients = $informed_users;

            $this->send_mail_by_reason_to_recipients($email_recipients, $item_restriction['item_id'], 'restriction_' . $item_restriction['restriction_type'], $item_restriction['date_start'], $validation_result['data']['date_end'], $item_restriction['restriction_hint']);

            //add users to informed users that got email regarding creation
            foreach($informed_users as $informed_user) {
              if(!in_array($informed_user->ID, $item_restriction['informed_user_ids']))
              $item_restriction['informed_user_ids'][] = $informed_user->ID;
            }
          }

          //update end date of restriction
          $item_restriction = CB_Item_Usage_Restriction::adjust_date_end($item_restriction, $validation_result['data']['date_end'], $validation_result['data']['update_comment']);
          CB_Item_Usage_Restriction::update_item_restriction($item_restriction['item_id'], $item_restriction);

          //if total breakdown - set new end date of blocking booking
          if($item_restriction['restriction_type'] == 1) {
            $this->update_booking_date_end( $item_restriction['booking_id'], $validation_result['data']['date_end'] );
          }

          $informed_users = array();

          //send update emails
          foreach ($bookings_for_update_email as $booking) {
            $user = get_user_by('id', $booking->user_id);
            if(!in_array($user, $informed_users)) {
              array_push($informed_users, $user);
            }
          }

          //ensure blocking user is informed even when blocking booking is in past or no blocking booking was created
          if(($item_restriction['restriction_type'] == 1 && count($bookings_for_update_email) == 0) || $item_restriction['restriction_type'] == 2) {
            array_push($informed_users, $this->blocking_user);
          }

          // update email to partners, etc.
          $responsible_users = $this->consider_responsible_users ? $this->find_responsible_users_by_item_and_location($item_restriction['item_id'], $location_id) : array();

          //get email adresses for additional notifications
          $additional_email_recipients = $item_restriction['additional_email_recipients'];

          $email_recipients = array_merge($informed_users, $responsible_users, $additional_email_recipients);

          $this->send_mail_by_reason_to_recipients($email_recipients, $item_restriction['item_id'], 'edit_restriction', $item_restriction['date_start'], $validation_result['data']['date_end'], $validation_result['data']['update_comment']);

          //show message on admin page
          $message = item_usage_restriction\__('RESTRICTION_EDITED', 'commons-booking-item-usage-restriction', 'The restriction was edited successfully.');
          $class = 'notice notice-success';
          echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';

        }
        else {
          $message = item_usage_restriction\__('END_DATE_TO_LOW', 'commons-booking-item-usage-restriction', 'end date is not allowed to be before start date');
          $class = 'notice notice-error';
          echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
        }

      }
      else {
        $message = item_usage_restriction\__('NO_RESTRICTION_TO_UPDATE', 'commons-booking-item-usage-restriction', 'unable to find usage restriction to update');
        $class = 'notice notice-error';
        echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
      }

    }
    else {
      $error_list = str_replace(',', ', ', implode(",", $validation_result['errors']));
      $message = item_usage_restriction\__('INPUT_ERRORS_OCCURED', 'commons-booking-item-usage-restriction', 'There are input errors in the request') . ': ' . $error_list;
      $class = 'notice notice-error';
      echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
    }
  }

  function delete_restriction() {

    $validation_result = $this->validate_delete_restriction_form_input();

    if($validation_result) {
      $item_restriction = $this->find_item_usage_restriction($validation_result['item_id'], $validation_result['created_by_user_id'], $validation_result['created_at_timestamp']);

      if(isset($item_restriction)) {

        //delete booking if there is one
        //var_dump($item_restriction['booking_id']);
        if($item_restriction['booking_id']) {
          global $wpdb;

          $table_name = $wpdb->prefix . 'cb_bookings';
          $wpdb->query("DELETE FROM $table_name WHERE id =" . $item_restriction['booking_id']);
        }

        //send email to users that have to be informed (have booking in restriction period that lays ahead or got additional email)
        $bookings = $this->fetch_current_and_future_bookings($item_restriction['date_start'], $item_restriction['date_end'], $item_restriction['item_id']);
        //var_dump($bookings);
        $email_recipients = array($this->blocking_user);
        foreach ($bookings as $booking) {
          $user = get_user_by('id', $booking->user_id);
          if(!in_array($user, $email_recipients)) {
            array_push($email_recipients, $user);
          }

        }

        foreach($item_restriction['responsible_user_ids'] as $responsible_user_id) {
          $user = get_user_by('id', $responsible_user_id);
          if(isset($user)) {
            array_push($email_recipients, $user);
          }
        }

        foreach ($item_restriction['additional_emails'] as $additional_email) {
          array_push($email_recipients, $additional_email);
        }

        $this->send_mail_by_reason_to_recipients($email_recipients, $item_restriction['item_id'], 'delete_restriction', $item_restriction['date_start'], $item_restriction['date_end'], $validation_result['delete_comment']);

        //remove restriction from item
        $item_restrictions = CB_Item_Usage_Restriction::get_item_restrictions($validation_result['item_id']);
        CB_Item_Usage_Restriction::remove_item_restriction($item_restriction['item_id'], $item_restrictions, $item_restriction['index']);

        $message = item_usage_restriction\__('RESTRICTION_DELETED', 'commons-booking-item-usage-restriction', 'The restriction was deleted successfully.');
        $class = 'notice notice-success';
        echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
      }
    }
  }

  function create_restriction($form_values) {
    $keep_form_values = false;

    $validation_result = $this->validate_create_restriction_form_input();
    //var_dump($validation_result);
    //echo'<br><br>';

    if(count($validation_result['errors']) > 0) {
      $error_list = str_replace(',', ', ', implode(",", $validation_result['errors']));
      $message = item_usage_restriction\__('INPUT_ERRORS_OCCURED', 'commons-booking-item-usage-restriction', 'There are input errors in the request') . ': ' . $error_list;
      $class = 'notice notice-error';
      echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';

      $keep_form_values = true;
    }
    else {
      $create_restriction_result = $this->handle_create_restriction_form_submit($validation_result['data']);

      if(!$create_restriction_result) {
        $keep_form_values = true;
      }
    }

    //keep form values
    if($keep_form_values) {
      if(!isset($validation_result['errors']['item_id'])) {
        $form_values['item_id'] = $validation_result['data']['item_id'];
      }

      $form_values['date_start'] = $validation_result['data']['date_start_valid'] ? $validation_result['data']['date_start_valid'] : $date_start;
      $form_values['date_end'] = $validation_result['data']['date_end_valid'] ? $validation_result['data']['date_end_valid'] : $date_end;

      $form_values['restriction_hint'] = $validation_result['data']['restriction_hint'];
      $form_values['additional_emails'] = implode(', ', $validation_result['data']['additional_emails']);

      if(!isset($validation_result['errors']['restriction_type'])) {
        $form_values['restriction_type'] = $validation_result['data']['restriction_type'];
      }
    }

    return $form_values;
  }
  /**
  * find usage restriction that matches the given properties
  **/
  function find_item_usage_restriction($item_id, $created_by_user_id, $created_at_timestamp) {

    //iterate existing restrictions and delete the wanted one
    $item_restrictions = CB_Item_Usage_Restriction::get_item_restrictions($item_id);

    $item_restriction = null;
    $restriction_index = -1;
    foreach ($item_restrictions as $index =>$item_restriction) {

      if($item_restriction['created_at']->getTimestamp() == $created_at_timestamp && $item_restriction['created_by_user_id'] == $created_by_user_id) {
         $restriction_index = $index;

        break;
      }
    }

    if(isset($item_restriction)) {
      $item_restriction['index'] = $restriction_index;
    }

    return $item_restriction;
  }

  function validate_edit_restriction_form_input() {

    $data = array();
    $errors = array();

    $data['item_id'] = intval($_POST['item_id']);
    $data['created_by_user_id'] = intval($_POST['created_by_user_id']);
    $data['created_at_timestamp'] = intval($_POST['created_at_timestamp']);
    $data['date_end'] = $_POST['date_end'];
    $data['date_end_valid'] = isset($_POST['date_end']) && strlen($_POST['date_end']) > 0 ? DateTime::createFromFormat('Y-m-d', $_POST['date_end']) : null;
    $data['update_comment'] = sanitize_text_field($_POST['update_comment']);

    if(!in_array($data['item_id'], $this->valid_cb_item_ids) || !$data['created_by_user_id'] || !$data['created_at_timestamp']) {

      $errors['item_id'] = item_usage_restriction\__('MISSING_RESTRICTION_PROPERTIES', 'commons-booking-item-usage-restriction', 'missing or invalid properties of usage restriction ');
    }

    if(!isset($data['date_end_valid'])) {
      $errors['date_end'] = item_usage_restriction\__('END_DATE_INVALID', 'commons-booking-item-usage-restriction', 'invalid end date');
    }
    else {
      $data['date_end_valid']->setTime( 23, 59, 59 );
    }

    return array('data' => $data, 'errors' => $errors);

  }

  function validate_delete_restriction_form_input() {

    $data = array();
    $errors = array();

    $data['item_id'] = intval($_POST['item_id']);
    $data['created_by_user_id'] = intval($_POST['created_by_user_id']);
    $data['created_at_timestamp'] = intval($_POST['created_at_timestamp']);
    $data['delete_comment'] = sanitize_text_field($_POST['delete_comment']);

    if(in_array($data['item_id'], $this->valid_cb_item_ids) && $data['created_by_user_id'] && $data['created_at_timestamp']) {

      return $data;
    }
    else {
      return false;
    }

  }

  /**
  * validates the input of the form to create a new restriction
  **/
  function validate_create_restriction_form_input() {

    //validation properties
    $data = array();
    $errors = array();

    $data['date_start_valid'] = isset($_POST['date_start']) && strlen($_POST['date_start']) > 0 ? DateTime::createFromFormat('Y-m-d', $_POST['date_start']) : null;
    $data['date_end_valid'] = isset($_POST['date_end']) && strlen($_POST['date_end']) > 0 ? DateTime::createFromFormat('Y-m-d', $_POST['date_end']) : null;
    $data['item_id'] = intval($_POST['item_id']);
    $data['restriction_type'] = intval($_POST['restriction_type']);
    $data['restriction_hint'] = sanitize_text_field($_POST['restriction_hint']);
    $data['additional_emails'] = [];

    $additional_emails = explode(',', str_replace(' ', '', $_POST['additional_emails']));
    foreach ($additional_emails as $email) {
      $sanitized_email = sanitize_email( $email );

      if($sanitized_email) {
        array_push($data['additional_emails'], $sanitized_email);
      }

    }

    if(!in_array($data['item_id'], $this->valid_cb_item_ids)) {
      $errors['item_id'] = item_usage_restriction\__('ITEM_INVALID', 'commons-booking-item-usage-restriction', 'invalid item');
    }

    if($data['date_start_valid']) {

      $data['date_start_valid']->setTime( 0, 0, 0 );
      $data['date_start'] = $_POST['date_start'];

    }
    else {
      $errors['date_start'] = item_usage_restriction\__('START_DATE_INVALID', 'commons-booking-item-usage-restriction', 'invalid start date');
    }

    //end date must be after start date
    if($data['date_end_valid']) {

      $data['date_end_valid']->setTime( 23, 59, 59 );
      $data['date_end'] = $_POST['date_end'];

      if($data['date_start_valid'] && !isset($errors['date_start'])) {
        if($data['date_end_valid'] < $data['date_start_valid']) {
          $errors['date_end'] = item_usage_restriction\__('END_DATE_TO_LOW', 'commons-booking-item-usage-restriction', 'end date is not allowed to be before start date');
        }
      }

    }
    else {
        $errors['date_end'] = item_usage_restriction\__('END_DATE_INVALID', 'commons-booking-item-usage-restriction', 'invalid end date');
    }

    if($data['restriction_type'] != 1 && $data['restriction_type'] != 2) {
      $errors['restriction_type'] = item_usage_restriction\__('RESTRICTION_TYPE_INVALID', 'commons-booking-item-usage-restriction', 'invalid restriction type');
    }

    if(strlen($data['restriction_hint']) == 0) {
      $errors['restriction_hint'] = item_usage_restriction\__('RESTRICTION_HINT_EMPTY', 'commons-booking-item-usage-restriction', 'restriction hint has to be filled out');
    }

    return array('data' => $data, 'errors' => $errors);
  }

  /**
  * check if already an restriction of type 'total breakdown' exists for the given item in wanted period (only if given type is also 'total breakdown')
  */
  function check_restriction_1_overlapping($item_id, $date_start_valid, $date_end_valid, $restriction_type) {

    $overlapping = false;

    if($restriction_type == 1) {

      //check for already existing restriction in timeframe
      $existing_restrictions = CB_Item_Usage_Restriction::get_item_restrictions($item_id);

      $overlapping = false;
      foreach ($existing_restrictions as $existing_restriction) {

        //not overlapping if existing restriction ends before start date or starts after end date
        $overlapping = $existing_restriction['date_end_valid'] < $date_start_valid || $existing_restriction['date_start_valid'] > $date_end_valid ? false : true;

        if($overlapping && $existing_restriction['restriction_type'] == 1) {
          break;
        }
      }
    }

    return $overlapping;
  }

  function handle_create_restriction_form_submit($data) {

    $overlapping = $this-> check_restriction_1_overlapping($data['item_id'], $data['date_start_valid'], $data['date_end_valid'], $data['restriction_type']);

    if($overlapping) {
      $message = item_usage_restriction\__('RESTRICTION_FOR_PERIOD_ALREADY_EXISTING', 'commons-booking-item-usage-restriction', "There's already a restriction for the given period.");
      $class = 'notice notice-error';
      echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
      return false;
    }

    //check if location (timeframe) exists
    $cb_booking = new CB_Booking();
    $location_id = $cb_booking->get_booking_location_id($data['date_start'], $data['date_end'], $data['item_id']);
    if(!$location_id) {
      $message = item_usage_restriction\__('NO_TIMEFRAME', 'commons-booking-item-usage-restriction', "There's no timeframe for the given period. You've to create one before you can set a restriction.");
      $class = 'notice notice-error';
      echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
      return false;
    }

    $booking_needed = false;
    $informed_users = array();

    // if total breakdown: create booking to block period of restriction
    if($data['restriction_type'] == 1) {
      $booking_needed = true;

      $booking_id = $this->create_booking($data['date_start'], $data['date_end'], $data['item_id'], $this->blocking_user->ID, 'confirmed');

    }
    else {
      array_push($informed_users, $this->blocking_user);

    }

    //create item restriction and send emails
    if(!$booking_needed || ($booking_needed && $booking_id)) {

      //get bookings in given period and add users to list of user that have to be informed: only users with bookings that end today or in the future have to be informed
      $bookings = $this->fetch_current_and_future_bookings($data['date_start'], $data['date_end'], $data['item_id']);

      foreach ($bookings as $booking) {
        $user = get_user_by('id', $booking->user_id);
        if(!in_array($user, $informed_users)) {
          array_push($informed_users, $user);
        }
      }

      //ensure blocking user is informed even when blocking booking is in past
      if($booking_needed && count($bookings) == 0) {
        array_push($informed_users, $this->blocking_user);
      }

      $responsible_users = $this->consider_responsible_users ? $this->find_responsible_users_by_item_and_location($data['item_id'], $location_id) : array();

      //get email adresses for additional notifications (comma seperated list)
      $additional_email_recipients = $data['additional_emails'];

      $data['booking_id'] = $booking_needed ? $booking_id : null;
      CB_Item_Usage_Restriction::add_item_restriction($data, $informed_users, $additional_email_recipients, $responsible_users);

      $email_recipients = array_merge($informed_users, $responsible_users, $additional_email_recipients);

      $this->send_mail_by_reason_to_recipients($email_recipients, $data['item_id'], 'restriction_' . $data['restriction_type'], $data['date_start'], $data['date_end'], $data['restriction_hint']);

      $message = item_usage_restriction\__('RESTRICTION_CREATED', 'commons-booking-item-usage-restriction', 'The restriction was created successfully.');
      $class = 'notice notice-success';
      echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';

      return true;
    }

    return false;

  }

  /**
  * based on Advanced Custom Fields
  **/
  function find_responsible_users_by_item_and_location($item_id, $location_id) {
    //user_locations
    $args = array(
    	'meta_query' => array(
    		'relation' => 'OR',
    			array(
    				'key'     => 'user_items',
    				'value'   => '"'.$item_id.'"',
    	 			'compare' => 'LIKE'
    			),
    			array(
            'key'     => 'user_locations',
    				'value'   => '"'.$location_id.'"',
    	 			'compare' => 'LIKE'
    			)
    	)
    );

    $user_query = new WP_User_Query( $args );

    return $user_query->get_results();
  }

  /**
  * create a booking with given properties
  */
  function create_booking($date_start, $date_end, $item_id, $user_id, $status) {

    $cb_booking = new CB_Booking();

    //set wanted user
    $cb_booking->user_id = $user_id;

    //create booking (pending)
    $cb_booking->hash = $cb_booking->create_hash();
    $booking_id = $cb_booking->create_booking( $date_start, $date_end, $item_id);

    if($booking_id) {

      //set status - (default is pending - it will be deleted by Commons Booking cronjob, if it's not confirmed)
      //set_booking_status is a private method, it has to be made accessible first
      $method = new ReflectionMethod('CB_Booking', 'set_booking_status');
      $method->setAccessible(true);
      $method->invoke($cb_booking, $booking_id, $status);

    }

    return $booking_id;

  }

  private function update_booking_date_end( $booking_id, $date_end ) {

      global $wpdb;
      $table_bookings = $wpdb->prefix . 'cb_bookings';

      $wpdb->query(
          "
          UPDATE $table_bookings
          SET date_end = '" . $date_end . "'
          WHERE id = $booking_id
          "
      );

  }

  function load_settings() {
    $blocking_user_id = get_option('cb_item_restriction_blocking_user_id', null);
    if($blocking_user_id) {
      $this->blocking_user = get_user_by('id', $blocking_user_id);
    }

    $this->email_message = array(
      'restriction_1' =>
        array(
          'subject' => get_option('cb_item_restriction_type_1_email_subject', ''),
          'body' => get_option('cb_item_restriction_type_1_email_body', '')
        ),
      'restriction_2' =>
        array(
          'subject' => get_option('cb_item_restriction_type_2_email_subject', ''),
          'body' => get_option('cb_item_restriction_type_2_email_body', '')
        ),
      'edit_restriction' =>
        array(
          'subject' => get_option('cb_item_restriction_edit_email_subject', ''),
          'body' => get_option('cb_item_restriction_edit_email_body', '')
        ),
      'delete_restriction' =>
        array(
          'subject' => get_option('cb_item_restriction_delete_email_subject', ''),
          'body' => get_option('cb_item_restriction_delete_email_body', '')
        )

    );

    $this->consider_responsible_users = get_option('cb_item_restriction_consider_responsible_users', false);
  }

  /**
  * fetches bookings in the given period that are not in the past
  **/
  function fetch_current_and_future_bookings($date_start, $date_end, $item_id) {
    $date_start_timestamp = strtotime($date_start);
    $date_end_timestamp = strtotime($date_end);

    $today_datetime = new DateTime();
    $today_datetime->setTime( 0, 0, 0 );
    $today_timestamp = $today_datetime->getTimestamp();

    $bookings = array();

    if($date_end_timestamp >= $today_timestamp) {
      $date_start = $date_start_timestamp >= $today_timestamp ? $date_start : $today_datetime->format('Y-m-d');

      $bookings = $this->fetch_bookings_in_period($date_start, $date_end, $item_id);

    }

    return $bookings;
  }

  /**
  * fetches bookings in period determined by start and end date from db for given item
  */
  function fetch_bookings_in_period($date_start, $date_end, $item_id) {
    global $wpdb;

    //get bookings data
    $table_name = $wpdb->prefix . 'cb_bookings';
    $select_statement = "SELECT * FROM $table_name WHERE item_id = %d ".
                        "AND ((date_start BETWEEN '".$date_start."' ".
                        "AND '".$date_end."') ".
                        "OR (date_end BETWEEN '".$date_start."' ".
                        "AND '".$date_end."') ".
                        "OR (date_start < '".$date_start."' ".
                        "AND date_end > '".$date_end."')) ".
                        "AND (status = 'pending' OR status = 'confirmed')";

    $prepared_statement = $wpdb->prepare($select_statement, $item_id);

    $bookings_result = $wpdb->get_results($prepared_statement);

    return $bookings_result;
  }

  function send_mail_by_reason_to_recipients($email_recipients, $item_id, $reason, $date_start, $date_end, $hint = '') {

    foreach ($email_recipients as $email_recipient) {
      $user_data = array();

      if(is_string($email_recipient)) {
        $user_data['first_name'] = '';
        $user_data['last_name'] = '';
        $user_data['user_email'] = $email_recipient;

      }
      else {
        $user_data['first_name'] = $email_recipient->first_name;
        $user_data['last_name'] = $email_recipient->last_name;
        $user_data['user_email'] = $email_recipient->user_email;
      }

      $this->send_mail_by_reason($item_id, $reason, $date_start, $date_end, $user_data, $hint);
    }
  }

  function send_mail_by_reason($item_id, $reason, $date_start, $date_end, $user_data, $hint = '') {

    $item = get_post($item_id);

    $subject_template = $this->email_message[$reason]['subject'];
    $body_template = $this->email_message[$reason]['body'];

    $mail_vars = $this->create_mail_vars($item, $date_start, $date_end, $user_data, $hint);

    return $this->send_mail($user_data['user_email'], $subject_template, $body_template, $mail_vars);
  }

  function create_mail_vars($item, $date_start, $date_end, $user_data, $hint) {

    return array(
      'first_name' => $user_data['first_name'],
      'last_name' => $user_data['last_name'],
      'date_start' => date_i18n( get_option( 'date_format' ), strtotime($date_start) ),
      'date_end' => date_i18n( get_option( 'date_format' ), strtotime($date_end) ),
      'item_name' => $item->post_title,
      'hint' => $hint
    );
  }

  function send_mail($to, $subject_template, $body_template, $mail_vars) {

    $cb_booking = new CB_Booking();

    $sender_from_email = $cb_booking->settings->get_settings( 'mail', 'mail_from');
    $sender_from_name = $cb_booking->settings->get_settings( 'mail', 'mail_from_name');
    $confirmation_bcc = $cb_booking->settings->get_settings( 'mail', 'mail_bcc');

    // if custom email adress AND name is specified in settings use them, otherwise fall back to standard
    if ( ! empty ( $sender_from_name ) && ! empty ( $sender_from_email )) {
        $headers[] = 'From: ' . $sender_from_name . ' <' . $sender_from_email . '>';
    }

    // if BCC: ist specified, send a copy to the address
    if ( ! empty ( $confirmation_bcc ) ) {
        $headers[] = 'BCC: ' . $confirmation_bcc . "\r\n";
    }

    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $subject = replace_template_tags( $subject_template, $mail_vars);
    $body = replace_template_tags( $body_template, $mail_vars);

    return wp_mail( $to, $subject, $body, $headers );

  }

}

?>
