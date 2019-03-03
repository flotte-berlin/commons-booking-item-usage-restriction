<?php

/*
Plugin Name:  Commons Booking Item Usage Restriction
Plugin URI:   https://github.com/flotte-berlin/commons-booking-item-usage-restriction
Description:  Ein Plugin in Ergänzung zu Commons Booking, das es erlaubt aus dem Admin-Bereich heraus NutzerInnen über temporäre Einschränkungen/Totalausfälle von Items zu informieren, die Buchbarkeit einzuschränken und diese Fälle zu verwalten
Version:      0.1.2
Author:       poilu
Author URI:   https://github.com/poilu
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/

define( 'CB_ITEM_USAGE_RESTRICTION_PATH', plugin_dir_path( __FILE__ ) );

require_once( CB_ITEM_USAGE_RESTRICTION_PATH . 'functions/translate.php' );

load_plugin_textdomain( 'commons-booking-item-usage-restriction', false, 'commons-booking-item-usage-restriction/languages/' );

require_once( CB_ITEM_USAGE_RESTRICTION_PATH . 'classes/class-cb-item-usage-restriction.php' );
require_once( CB_ITEM_USAGE_RESTRICTION_PATH . 'classes/class-cb-item-usage-restriction-settings.php' );
require_once( CB_ITEM_USAGE_RESTRICTION_PATH . 'classes/class-cb-item-usage-restriction-admin.php' );

$cb_item_restriction_settings = new CB_Item_Usage_Restriction_Settings();
$cb_item_restriction_settings->prepare_settings();
add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), array($cb_item_restriction_settings, 'add_settings_link') );

$cb_item_usage_restriction_admin = new CB_Item_Usage_Restriction_Admin();

function load_additional_js() {
  wp_enqueue_script( 'jquery-ui-dialog' );
  wp_enqueue_style( 'wp-jquery-ui-dialog' );
}

add_action('admin_init', 'load_additional_js');

add_action( 'admin_menu', array($cb_item_usage_restriction_admin, 'add_plugin_admin_menu'), 11);

$cb_item_usage_restriction = new CB_Item_Usage_Restriction();
add_filter( 'the_content', array($cb_item_usage_restriction, 'render_current_restrictions') );
