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
            <option value="<?= $cb_item->ID ?>" <?= $cb_item->ID == $form_values['item_id'] ? 'selected' : '' ?>><?= $cb_item->post_title ?></option>
          <?php endforeach; ?>
        </select>

        <label for="date_start"><?= item_usage_restriction\__( 'FROM', 'commons-booking-item-usage-restriction', 'from') ?> </label>
        <input type="date" name="date_start" value="<?= $form_values['date_start']->format('Y-m-d') ?>">
        <label for="date_end"><?= item_usage_restriction\__( 'UNTIL', 'commons-booking-item-usage-restriction', 'until estimated') ?> </label>
        <input type="date" name="date_end" value="<?= $form_values['date_end']->format('Y-m-d') ?>">

        <?php
          $date_start = $form_values['date_start'] ?  $form_values['date_start'] : new DateTime();
          $date_end = $form_values['date_end'] ? $form_values['date_end'] : new DateTime();
          $chart_date_end = (new DateTime())->setTimestamp(strtotime($date_end->format('Y-m-d').'+ 2 months'));
          $chart_item_id = isset($item_id) ? $item_id : (isset($cb_items[0]) ? $cb_items[0]->ID : '');
          echo do_shortcode('[cb_bookings_gantt_chart item_id="' . $chart_item_id .'?>" date_start="' . $date_start->format('Y-m-d') . '" date_end="' . $chart_date_end->format('Y-m-d') . '"]');
        ?>
        <br>
      </div>

      <label for="date_end"><?=  item_usage_restriction\__( 'AS', 'commons-booking-item-usage-restriction', 'as') ?>:</label><br>

      <div style="margin-left: 20px">

        <input type="radio" name="restriction_type" value="1" <?= $form_values['restriction_type'] == 1 ? 'checked' : '' ?>><span style="background-color: #ff6666;"> <?= item_usage_restriction\__( 'RESTRICTION_TYPE_1', 'commons-booking-item-usage-restriction', 'total breakdown') ?></span><br>
        <input type="radio" name="restriction_type" value="2" <?= $form_values['restriction_type'] == 2 ? 'checked' : '' ?>><span style="background-color: #ffdf80;"> <?= item_usage_restriction\__( 'RESTRICTION_TYPE_2', 'commons-booking-item-usage-restriction', 'usable to a limited extend') ?></span><br>

      </div>

      <label for="restriction_hint"><?= item_usage_restriction\__( 'WITH_HINT', 'commons-booking-item-usage-restriction', 'with hint') ?>:</label><br>

      <div style="margin-left: 20px">
        <textarea style="width: 500px; height: 100px;" name="restriction_hint" required><?= $form_values['restriction_hint'] ?></textarea><br>
      </div>

      <label for="additional_emails"><?= item_usage_restriction\__( 'SEND_ADDITIONAL_EMAIL_TO', 'commons-booking-item-usage-restriction', 'send additional email notification to (comma separated list)') ?>:</label><br>

      <div style="margin-left: 20px">
        <input type="text" style="width: 500px;" name="additional_emails" value="<?= $form_values['additional_emails'] ?>">
      </div>
      <br>
      <input class="button action" value="<?= item_usage_restriction\__( 'EXECUTE', 'commons-booking-item-usage-restriction', 'make it so') ?>" type="submit">
    </form>

  <?php else: ?>
    <p><?= item_usage_restriction\__( 'NO_ITEMS_AVAILABLE', 'commons-booking-item-usage-restriction', "There've to be items, in order to set usage restrictions.") ?></p>
  <?php endif; ?>

<?php else: ?>
  <div id="message" class="notice notice-warning">
    <p>
      <?= item_usage_restriction\__( 'SETTINGS_NOT_CORRECT', 'commons-booking-item-usage-restriction', "To restrict usage of items you have to define a blocking user and set email templates in the plugin settings.") ?>
      <a href="options-general.php?page=commons-booking-item-usage-restriction"> <?= __('Settings') ?></a>
    </p>
  </div>
<?php endif; ?>

<script>
  jQuery( document ).ready(function($) {
    <?php
      $item_ids = [];
      foreach ($cb_items as $cb_item) {
        $item_ids[] = $cb_item->ID;
      }
    ?>
    var nonces = <?= json_encode(CB_Bookings_Gantt_Chart_Shortcode::create_item_chart_nonces($item_ids)); ?>

    $('.cb-booking-gantt-chart-button').click((ev) => {
      ev.preventDefault();
    });

    $('select[name="item_id"]').first().change(function() {
      console.log('change item to: ', $(this).val());

      $('.cb-booking-gantt-chart-button').attr('data-item_id', $(this).val());
      $('.cb-booking-gantt-chart-button').attr('data-nonce', nonces[$(this).val()]);

      $('.cb-bookings-gantt-chart-close').click();
    });
  });
</script>