<?php

class CB_Item_Usage_Restriction_Settings {

  /**
  * prepare settings
  */
  public function prepare_settings() {

    //load translation
    $lang_path = 'commons-booking-item-usage-restriction/languages/';
    load_plugin_textdomain( 'commons-booking-item-usage-restriction', false, $lang_path );

    add_action('admin_menu', function() {
        add_options_page( item_usage_restriction\__('SETTINGS_TITLE', 'commons-booking-item-usage-restriction', 'Settings for item usage restriction'), item_usage_restriction\__('SETTINGS_MENU', 'commons-booking-item-usage-restriction', 'Item Usage Restriction' ), 'manage_options', 'commons-booking-item-usage-restriction', array($this, 'render_options_page') );
    });

    add_action( 'admin_init', function() {
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_blocking_user_id' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_type_1_email_subject' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_type_1_email_body' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_type_2_email_subject' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_type_2_email_body' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_edit_email_subject' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_edit_email_body' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_delete_email_subject' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_delete_email_body' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_consider_responsible_users' );
      register_setting( 'cb-item-usage-restriction-settings', 'cb_item_restriction_appears_always_in_article_description' );
    });

  }

  public function add_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=commons-booking-item-usage-restriction">' . __('Settings') . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
  }

  public function render_options_page() {
    $users = get_users();

    include_once( CB_ITEM_USAGE_RESTRICTION_PATH . 'templates/settings-page-template.php');
  }
}

?>
