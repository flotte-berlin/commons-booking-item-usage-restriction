<?php if($settings_correct): ?>

  <h3><?= item_usage_restriction\__( 'EXISTING_USAGE_RESTRICTIONS', 'commons-booking-item-usage-restriction', "Existing Usage Restrictions") ?></h3>

  <form method="GET">
    <label for="item_id"><?= item_usage_restriction\__( 'SHOW_RESTRICTIONS_FOR', 'commons-booking-item-usage-restriction', 'Restrictions for') ?>:</label>
    <input type="hidden" name="page" value="<?= $page ?>">

    <select name="restriction_list_cb_item_id">
      <option value="" ><?= item_usage_restriction\__( 'NONE', 'commons-booking-item-usage-restriction', '- none -') ?></option>
      <?php foreach ($cb_items as $cb_item): ?>
        <option value="<?= $cb_item->ID ?>" <?= $cb_item->ID == $restriction_list_cb_item_id ? 'selected' : '' ?>><?= $cb_item->post_title ?></option>
      <?php endforeach; ?>
    </select>

    <input class="button action" value="<?= item_usage_restriction\__( 'SHOW', 'commons-booking-item-usage-restriction', 'show') ?>" type="submit">
  </form>

  <?php if($restriction_list_cb_item_id): ?>

    <?php if(isset($item_restrictions) && count($item_restrictions) > 0): ?>
    <table class="wp-list-table" style="width: 100%; margin-top: 20px;">
      <thead>
        <tr style="background-color: #fff">
          <th><?= item_usage_restriction\__( 'START_DATE', 'commons-booking-item-usage-restriction', 'start') ?></th>
          <th><?= item_usage_restriction\__( 'END_DATE', 'commons-booking-item-usage-restriction', 'end') ?></th>
          <th><?= item_usage_restriction\__( 'RESTRICTION_TYPE', 'commons-booking-item-usage-restriction', 'restriction') ?></th>
          <th style="width: 300px;"><?= item_usage_restriction\__( 'HINT', 'commons-booking-item-usage-restriction', 'hint') ?></th>
          <th><?= item_usage_restriction\__( 'CREATED_BY', 'commons-booking-item-usage-restriction', 'created by') ?></th>
          <th><?= item_usage_restriction\__( 'CREATED_AT', 'commons-booking-item-usage-restriction', 'created at') ?></th>
          <th style="width: 300px;"><?= item_usage_restriction\__( 'INFORMED_USERS', 'commons-booking-item-usage-restriction', 'informed users') ?></th>
          <th style="width: 300px;"><?= item_usage_restriction\__( 'ADDITIONAL_EMAILS', 'commons-booking-item-usage-restriction', 'additional emails') ?></th>
          <?php if($consider_responsible_users): ?>
            <th style="width: 200px;"><?= item_usage_restriction\__( 'RESPONSIBLE_USERS', 'commons-booking-item-usage-restriction', 'responsible users') ?></th>
          <?php endif; ?>
          <th><?= item_usage_restriction\__( 'ACTIONS', 'commons-booking-item-usage-restriction', 'actions') ?></th>
        <tr>
      </thead>

      <tbody style="">
        <?php foreach ($item_restrictions as $item_restriction): ?>

          <tr style="height: 40px; background-color: <?= $item_restriction['restriction_type'] == 1 ? '#ff6666' : '#ffdf80' ?>;">
            <td style="padding: 5px;"><?= date_i18n( get_option( 'date_format' ), strtotime($item_restriction['date_start'])) ?></td>
            <td style="padding: 5px;"><?= date_i18n( get_option( 'date_format' ), strtotime($item_restriction['date_end'])) ?></td>
            <td style="padding: 5px;">
              <?= $item_restriction['restriction_type'] == 1 ? item_usage_restriction\__( 'RESTRICTION_TYPE_1', 'commons-booking-item-usage-restriction', 'total breakdown') : item_usage_restriction\__( 'RESTRICTION_TYPE_2', 'commons-booking-item-usage-restriction', 'usable to a limited extend') ?>
              <?= $item_restriction['restriction_type'] == 1 ? '<br>(' . item_usage_restriction\__( 'BLOCK_BY_BOOKING', 'commons-booking-item-usage-restriction', 'blocked by booking'). ': ' . $item_restriction['booking_id'] .')' : '' ?>
            </td>
            <td style="padding: 5px;"><?= $item_restriction['restriction_hint'] ?></td>
            <td style="padding: 5px;">
              <?php $created_by_user = get_user_by('id', $item_restriction['created_by_user_id']) ?>
              <a style="color: #444;" href="<?= get_edit_user_link( $item_restriction['created_by_user_id'] ) ?>"><?= $created_by_user->first_name . ' ' . $created_by_user->last_name ?></a>
            </td>
            <td style="padding: 5px;"><?= date_i18n( get_option( 'date_format' ), $item_restriction['created_at']->getTimestamp()) ?></td>
            <td style="padding: 5px;">
              <?php foreach ($item_restriction['informed_user_ids'] as $index => $user_id): ?>
                <?= $index > 0 ? ',&nbsp;' : '' ?>
                <?php $user = get_user_by('id', $user_id) ?>
                <a style="color: #444;" href="<?= get_edit_user_link( $user_id ) ?>"><?= $user->first_name . ' ' . $user->last_name ?></a>
              <?php endforeach; ?>
            </td>
            <td style="padding: 5px;">
              <?= implode(', ', $item_restriction['additional_emails']) ?>
            </td>
            <?php if($consider_responsible_users): ?>
              <td style="padding: 5px;">
                <?php foreach ($item_restriction['responsible_user_ids'] as $index => $user_id): ?>
                  <?= $index > 0 ? ',&nbsp;' : '' ?>
                  <?php $user = get_user_by('id', $user_id) ?>
                  <?php if($user): ?>
                    <a style="color: #444;" href="<?= get_edit_user_link( $user_id ) ?>"><?= $user->first_name . ' ' . $user->last_name ?></a>
                  <?php endif; ?>
                <?php endforeach; ?>
              </td>
            <?php endif; ?>
            <td style="padding: 5px;">
              <button class="cb-item-usage-restriction-delete button action" data-item_id="<?= $item_restriction['item_id'] ?>" data-date_start="<?= $item_restriction['date_start'] ?>" data-date_end="<?= $item_restriction['date_end'] ?>">
                <?= item_usage_restriction\__( 'DELETE_RESTRICTION', 'commons-booking-item-usage-restriction', 'delete') ?>
              </button>
              <?php //$today = new DateTime('today'); ?>
              <?php //if($item_restriction['date_end_valid'] >= $today): ?>
                <!--
                <button class="cb-item-usage-restriction-edit button action"><?= item_usage_restriction\__( 'EDIT_RESTRICTION', 'commons-booking-item-usage-restriction', 'edit') ?></button>
                -->
              <?php //endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p><?= item_usage_restriction\__( 'NO_RESTRICTIONS_EXISTING', 'commons-booking-item-usage-restriction', 'There are no restrictions for the chosen item.') ?></p>
    <?php endif; ?>
  <?php else: ?>
    <p><?= item_usage_restriction\__( 'CHOOSE_ITEM', 'commons-booking-item-usage-restriction', 'Please choose an item to show restrictions.') ?></p>
  <?php endif; ?>

  <!-- delete dialog-->
  <div id="cb-item-usage-restriction-delete-dialog" class="hidden" style="max-width:500px">
    <p><?= item_usage_restriction\__( 'DELETE_RESTRICTION_DIALOG', 'commons-booking-item-usage-restriction', 'The restriction will be deleted and all users with running or outstanding bookings for the item in the restriction period will be informed.') ?></p>

    <form id="cb-item-usage-restriction-delete-form" method="POST">
      <input type="hidden" name="action" value="delete-restriction">
      <input type="hidden" name="item_id" value="">
      <input type="hidden" name="date_start" value="">
      <input type="hidden" name="date_end" value="">
      <label for="delete_comment"><?= item_usage_restriction\__( 'DELETE_COMMENT', 'commons-booking-item-usage-restriction', 'write a comment') ?>:</label><br>
      <textarea style="width: 100%; height: 100px;" name="delete_comment"></textarea><br>
      <input style="float:right;" class="button action" value="<?= item_usage_restriction\__( 'CONFIRM', 'commons-booking-item-usage-restriction', 'confirm') ?>" type="submit">
    </form>
  </div>

  <script>
  jQuery(document).ready(function ($) {
    // initalise the dialog
    $('#cb-item-usage-restriction-delete-dialog').dialog({
      title: '<?= item_usage_restriction\__( 'DELETE_RESTRICTION_DIALOG_TITLE', 'commons-booking-item-usage-restriction', 'Delete Restriction') ?>',
      dialogClass: 'wp-dialog',
      autoOpen: false,
      draggable: false,
      width: 'auto',
      modal: true,
      resizable: false,
      closeOnEscape: true,
      position: {
        my: "center",
        at: "center",
        of: '#wpcontent'
      },
      open: function (event) {
        // close dialog by clicking the overlay behind it
        $('.ui-widget-overlay').bind('click', function(){
          $('#cb-item-usage-restriction-delete-dialog').dialog('close');
        })

        // hide close button, because of a styling issue
        $(".ui-dialog-titlebar-close").hide();

        var data = $(this).data()
        console.log(data);

        var $form = $('#cb-item-usage-restriction-delete-form');
        $form.find('input[name="item_id"]').val(data.item_id);
        $form.find('input[name="date_start"]').val(data.date_start);
        $form.find('input[name="date_end"]').val(data.date_end);
      },
      create: function () {

      },
    });
    // bind a button or a link to open the dialog
    $('.cb-item-usage-restriction-delete').click(function(e) {
      e.preventDefault();
      var data = $(e.target).data();
      var $dialog = $('#cb-item-usage-restriction-delete-dialog');
      $dialog.data(data);
      $dialog.dialog('open');
    });
  });
  </script>

<?php endif; ?>
