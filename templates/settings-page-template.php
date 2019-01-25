<div class="wrap">
  <h1><?= item_usage_restriction\__('SETTINGS_PAGE_HEADER', 'commons-booking-item-usage-restriction', 'Settings for usage restrictions') ?></h1>

  <p><?= item_usage_restriction\__('SETTINGS_DESCRIPTION', 'commons-booking-item-usage-restriction', 'These settings concern the usage restrictions of Commons Booking items.<br> For emails the following template tags are available: {{FIRST_NAME}}, {{LAST_NAME}}, {{DATE_START}}, {{DATE_END}}, {{ITEM_NAME}}, {{HINT}}') ?></p>

  <form method="post" action="options.php">
    <?php
      settings_fields( 'cb-item-usage-restriction-settings' );
      do_settings_sections( 'cb-item-usage-restriction-settings' );
    ?>

    <!-- blocking user -->
    <h2><?= item_usage_restriction\__('RESTRICTION_BLOCKING_USER_HEADER', 'commons-booking-item-usage-restriction', 'Blocking User') ?></h2>

    <p>
      <?= item_usage_restriction\__('RESTRICTION_BLOCKING_USER_DESCRIPTION', 'commons-booking-item-usage-restriction', 'The selected user is used to block bookings during the period of a total breakdown.') ?>
    </p>

    <select name="cb_item_restriction_blocking_user_id" placeholder="<?= item_usage_restriction\__( 'NAME', 'commons-booking-admin-booking', 'name') ?>...">
      <option value=""></option>
      <?php foreach ($users as $user): ?>
        <option value="<?= $user->ID ?>" <?= $user->ID == get_option('cb_item_restriction_blocking_user_id') ? 'selected' : '' ?>><?= $user->first_name ?> <?= $user->last_name ?> (<?= $user->display_name ?>)</option>
      <?php endforeach; ?>
    </select>

    <!-- total breakdown email -->
    <h2><?= item_usage_restriction\__('RESTRICTION_TOTAL_BREAKDOWN_EMAIL_HEADER', 'commons-booking-item-usage-restriction', 'Total Breakdown Email') ?></h2>

    <p>
      <?= item_usage_restriction\__('RESTRICTION_TOTAL_BREAKDOWN_EMAIL_DESCRIPTION', 'commons-booking-item-usage-restriction', 'This email is sent to inform users with bookings about a total breakdown of an item.') ?>
    </p>

    <table>

      <tr>
          <th><?= item_usage_restriction\__('EMAIL_SUBJECT', 'commons-booking-item-usage-restriction', 'email subject') ?></th>
          <td><input type="text" placeholder="<?= item_usage_restriction\__('RESTRICTION_TOTAL_BREAKDOWN_EMAIL_SUBJECT_PLACEHOLDER', 'commons-booking-item-usage-restriction', 'total breakdown of item {{ITEM_NAME}}') ?>" name="cb_item_restriction_type_1_email_subject" value="<?php echo esc_attr( get_option('cb_item_restriction_type_1_email_subject') ); ?>" size="50" /></td>
      </tr>
      <tr>
          <th><?= item_usage_restriction\__('EMAIL_CONTENT', 'commons-booking-item-usage-restriction', 'email content') ?></th>
          <td><textarea placeholder="<?= item_usage_restriction\__('RESTRICTION_TOTAL_BREAKDOWN_EMAIL_CONTENT_PLACEHOLDER', 'commons-booking-item-usage-restriction', 'Dear {{FIRST_NAME}}, the item is not usable ...') ?>" name="cb_item_restriction_type_1_email_body" rows="10" cols="53"><?php echo esc_attr( get_option('cb_item_restriction_type_1_email_body') ); ?></textarea></td>
      </tr>
    </table>

    <!-- limited usage email -->
    <h2><?= item_usage_restriction\__('RESTRICTION_LIMITED_USAGE_EMAIL_HEADER', 'commons-booking-item-usage-restriction', 'Limited Usage Email') ?></h2>

    <p>
      <?= item_usage_restriction\__('RESTRICTION_LIMITED_USAGE_EMAIL_DESCRIPTION', 'commons-booking-item-usage-restriction', 'This email is sent to inform users with bookings about the fact that an item is only usable to a limited extend.') ?>
    </p>

    <table>

      <tr>
          <th><?= item_usage_restriction\__('EMAIL_SUBJECT', 'commons-booking-item-usage-restriction', 'email subject') ?></th>
          <td><input type="text" placeholder="<?= item_usage_restriction\__('RESTRICTION_LIMITED_USAGE_EMAIL_SUBJECT_PLACEHOLDER', 'commons-booking-item-usage-restriction', 'limitation in usage of item {{ITEM_NAME}}') ?>" name="cb_item_restriction_type_2_email_subject" value="<?php echo esc_attr( get_option('cb_item_restriction_type_2_email_subject') ); ?>" size="50" /></td>
      </tr>
      <tr>
          <th><?= item_usage_restriction\__('EMAIL_CONTENT', 'commons-booking-item-usage-restriction', 'email content') ?></th>
          <td><textarea placeholder="<?= item_usage_restriction\__('RESTRICTION_LIMITED_USAGE_EMAIL_CONTENT_PLACEHOLDER', 'commons-booking-item-usage-restriction', 'Dear {{FIRST_NAME}}, the item is only usable to a limited extend ...') ?>" name="cb_item_restriction_type_2_email_body" rows="10" cols="53"><?php echo esc_attr( get_option('cb_item_restriction_type_2_email_body') ); ?></textarea></td>
      </tr>
    </table>

    <!-- restriction deleted email -->
    <h2><?= item_usage_restriction\__('RESTRICTION_DELETED_EMAIL_HEADER', 'commons-booking-item-usage-restriction', 'Restriction Deleted Email') ?></h2>

    <p>
      <?= item_usage_restriction\__('RESTRICTION_DELETED_EMAIL_DESCRIPTION', 'commons-booking-item-usage-restriction', 'This email is sent to inform users with bookings when a restriction was deleted.') ?>
    </p>

    <table>

      <tr>
          <th><?= item_usage_restriction\__('EMAIL_SUBJECT', 'commons-booking-item-usage-restriction', 'email subject') ?></th>
          <td><input type="text" placeholder="<?= item_usage_restriction\__('RESTRICTION_DELETED_EMAIL_SUBJECT_PLACEHOLDER', 'commons-booking-item-usage-restriction', 'restriction of item {{ITEM_NAME}} withdrawn') ?>" name="cb_item_restriction_delete_email_subject" value="<?php echo esc_attr( get_option('cb_item_restriction_delete_email_subject') ); ?>" size="50" /></td>
      </tr>
      <tr>
          <th><?= item_usage_restriction\__('EMAIL_CONTENT', 'commons-booking-item-usage-restriction', 'email content') ?></th>
          <td><textarea placeholder="<?= item_usage_restriction\__('RESTRICTION_DELETED_EMAIL_CONTENT_PLACEHOLDER', 'commons-booking-item-usage-restriction', 'Dear {{FIRST_NAME}}, the item is ready again ...') ?>" name="cb_item_restriction_delete_email_body" rows="10" cols="53"><?php echo esc_attr( get_option('cb_item_restriction_delete_email_body') ); ?></textarea></td>
      </tr>
    </table>

    <h2><?= item_usage_restriction\__('OTHER_OPTIONS', 'commons-booking-item-usage-restriction', 'Other Options') ?></h2>

    <table>
      <tr>
        <th><?= item_usage_restriction\__('SHOW_ALWAYS_IN_ARTICLE_DESCRIPTION', 'commons-booking-item-usage-restriction', 'Show Always') ?></th>
        <td>
            <label>
                <input type="checkbox" name="cb_item_restriction_appears_always_in_article_description" <?php echo esc_attr( get_option('cb_item_restriction_appears_always_in_article_description') ) == 'on' ? 'checked="checked"' : ''; ?> /><?= __('SHOW_EVEN_IF_NO_RESTRICTION', 'commons-booking-item-usage-restriction', 'Yes, even if no restriction to show.') ?>
            </label><br/>
        </td>
      </tr>

    </table>

    <input type="hidden" name="cb_item_restriction_consider_responsible_users" value="<?php echo esc_attr( get_option('cb_item_restriction_consider_responsible_users') ) ?>" />

    <?php submit_button(); ?>
  </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.4/js/standalone/selectize.js"></script>

<script>
jQuery('head').append('<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.4/css/selectize.min.css">');

jQuery('select[name=cb_item_restriction_blocking_user_id]').selectize({
    sortField: 'text'
});

jQuery('.selectize-control').css({
  'width': '300px',
  'display': 'inline-block',
  'vertical-align': 'top',
  'margin-top': '2px'
});

jQuery('.selectize-input').css({
  'padding': '4.5px',
  'border-radius': '0px'
});
</script>
