<h3><?= item_usage_restriction\__( 'CREATE_USAGE_RESTRICTION', 'commons-booking-item-usage-restriction', "Create Usage Restriction") ?></h3>

<p><?= item_usage_restriction\__( 'DESCRIPTION', 'commons-booking-item-usage-restriction', "This section allows the creation of usage restrictions for items.") ?></p>

<?php if($settings_correct): ?>

  <?php if(isset($cb_items) && count($cb_items) > 0): ?>

    <form method="POST">
      <input type="hidden" name="action" value="create-restriction">
      <label for="item_id"><?= item_usage_restriction\__( 'MARK_ITEM', 'commons-booking-item-usage-restriction', 'Mark item') ?>:</label><br>
      <div style="margin-left: 20px">

        <select name="item_id">
          <?php foreach ($cb_items as $cb_item): ?>
            <option value="<?= $cb_item->ID ?>" <?= $cb_item->ID == $item_id ? 'selected' : '' ?>><?= $cb_item->post_title ?></option>
          <?php endforeach; ?>
        </select>

        <label for="date_start"><?= item_usage_restriction\__( 'FROM', 'commons-booking-item-usage-restriction', 'from') ?> </label>
        <input type="date" name="date_start" value="<?= $date_start->format('Y-m-d') ?>">
        <label for="date_end"><?= item_usage_restriction\__( 'UNTIL', 'commons-booking-item-usage-restriction', 'until estimated') ?> </label>
        <input type="date" name="date_end" value="<?= $date_end->format('Y-m-d') ?>"><br>

      </div>

      <label for="date_end"><?=  item_usage_restriction\__( 'AS', 'commons-booking-item-usage-restriction', 'as') ?>:</label><br>

      <div style="margin-left: 20px">

        <input type="radio" name="restriction_type" value="1" <?= $restriction_type == 1 ? 'checked' : '' ?>> <?= item_usage_restriction\__( 'RESTRICTION_TYPE_1', 'commons-booking-item-usage-restriction', 'total breakdown') ?><br>
        <input type="radio" name="restriction_type" value="2" <?= $restriction_type == 2 ? 'checked' : '' ?>> <?= item_usage_restriction\__( 'RESTRICTION_TYPE_2', 'commons-booking-item-usage-restriction', 'usable to a limited extend') ?><br>

      </div>

      <label for="restriction_hint"><?= item_usage_restriction\__( 'WITH_HINT', 'commons-booking-item-usage-restriction', 'with hint') ?>:</label><br>

      <div style="margin-left: 20px">
        <textarea style="width: 500px; height: 100px;" name="restriction_hint"><?= $restriction_hint ?></textarea><br>
      </div>

      <label for="additional_emails"><?= item_usage_restriction\__( 'SEND_ADDITIONAL_EMAIL_TO', 'commons-booking-item-usage-restriction', 'send additional email notification to (comma separated list)') ?>:</label><br>

      <div style="margin-left: 20px">
        <input type="text" style="width: 500px;" name="additional_emails" value="<?= $additional_emails ?>">
      </div>
      <br>
      <input class="button action" value="<?= item_usage_restriction\__( 'EXECUTE', 'commons-booking-item-usage-restriction', 'make it so') ?>" type="submit">
    </form>

  <?php else: ?>
    <p><?= item_usage_restriction\__( 'NO_ITEMS_AVAILABLE', 'commons-booking-item-usage-restriction', "There've to be items, in order to set usage restrictions.") ?></p>
  <?php endif; ?>

<?php else: ?>
  <div id="message" class="notice notice-warning">
    <p><?= item_usage_restriction\__( 'SETTINGS_NOT_CORRECT', 'commons-booking-item-usage-restriction', "To restrict usage of items you have to define a blocking user and set email templates in the plugin settings.") ?></p>
  </div>
<?php endif; ?>
