<?php if($settings_correct): ?>

  <h3><?= item_usage_restriction\__( 'EXISTING_USAGE_RESTRICTIONS', 'commons-booking-item-usage-restriction', "Existing Usage Restrictions") ?></h3>

  <form method="GET">
    <input type="hidden" name="page" value="<?= $page ?>">
    <select name="restriction_list_type">
      <option value="1" <?= $restriction_list_type == 1 ? 'selected' : '' ?>><?= item_usage_restriction\__( 'EXISTING', 'commons-booking-item-usage-restriction', 'existing') ?></option>
      <option value="2" <?= $restriction_list_type == 2 ? 'selected' : '' ?>><?= item_usage_restriction\__( 'DELETED', 'commons-booking-item-usage-restriction', 'deleted') ?></option>
    </select>
    <label for="item_id"><?= item_usage_restriction\__( 'SHOW_RESTRICTIONS_FOR', 'commons-booking-item-usage-restriction', 'Restrictions for') ?></label>

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
          <th style="width: 80px;"><?= item_usage_restriction\__( 'START_DATE', 'commons-booking-item-usage-restriction', 'start') ?></th>
          <th style="width: 80px;"><?= item_usage_restriction\__( 'END_DATE', 'commons-booking-item-usage-restriction', 'end') ?></th>
          <th style="min-width: 300px;"><?= item_usage_restriction\__( 'HINT', 'commons-booking-item-usage-restriction', 'hint') ?></th>
          <th style="width: 150px;"><?= item_usage_restriction\__( 'CREATED_BY', 'commons-booking-item-usage-restriction', 'created by') ?></th>
          <th style="width: 100px;"><?= item_usage_restriction\__( 'CREATED_AT', 'commons-booking-item-usage-restriction', 'created at') ?></th>
          <?php if($consider_responsible_users): ?>
            <th style="width: 200px;"><?= item_usage_restriction\__( 'RESPONSIBLE_USERS', 'commons-booking-item-usage-restriction', 'responsible users') ?></th>
          <?php endif; ?>
          <th style="width: 200px;"><?= item_usage_restriction\__( 'ACTIONS', 'commons-booking-item-usage-restriction', 'actions') ?></th>
        <tr>
      </thead>

      <tbody style="">
        <?php foreach ($item_restrictions as $item_restriction): ?>

          <tr style="height: 40px; background-color: <?= $item_restriction['restriction_type'] == 1 ? '#ff6666' : '#ffdf80' ?>;">
            <td style="padding: 5px;"><?= date_i18n( get_option( 'date_format' ), strtotime($item_restriction['date_start'])) ?></td>
            <td style="padding: 5px;"><?= date_i18n( get_option( 'date_format' ), strtotime($item_restriction['date_end'])) ?></td>
            <td style="padding: 5px;"><?= $item_restriction['restriction_hint'] ?></td>
            <td style="padding: 5px;">
              <?php $created_by_user = get_user_by('id', $item_restriction['created_by_user_id']) ?>
              <a style="color: #444;" href="<?= get_edit_user_link( $item_restriction['created_by_user_id'] ) ?>"><?= $created_by_user->first_name . ' ' . $created_by_user->last_name ?></a>
            </td>
            <td style="padding: 5px;"><?= date_i18n( get_option( 'date_format' ), $item_restriction['created_at']->getTimestamp()) ?>
              <?php if ($item_restriction['restriction_type'] == 1) : ?>
                <span style="cursor: help;" class="dashicons dashicons-editor-help" title="<?= item_usage_restriction\__( 'BLOCK_BY_BOOKING', 'commons-booking-item-usage-restriction', 'blocked by booking') . ': ' . $item_restriction['booking_id'] ?>"></span>
              <?php endif; ?>
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

              <?php $informed_user_items = [];
              foreach ($item_restriction['informed_user_ids'] as $index => $user_id) {
                $user = get_user_by('id', $user_id);

                if(isset($user)) {
                  $user_item = array(
                    'link' => get_edit_user_link( $user_id ),
                    'first_name' => is_object($user) ? $user->first_name : '',
                    'last_name' => is_object($user) ? $user->last_name : ''
                  );

                  $informed_user_items[] = $user_item;
                }

              }

              $restriction_updates = [];
              if(isset($item_restriction["updates"])) {
                foreach ($item_restriction["updates"] as $update) {
                  $created_by_user = get_user_by('id', $update['created_by_user_id']);
                  $restriction_updates[] = [
                    'old_date_end' => date_i18n( get_option( 'date_format' ), strtotime($update['old_date_end'])),
                    'new_date_end' => isset($update['update_type']) && $update['update_type'] == 'delete' ? '- ' .  item_usage_restriction\__('DELETED_RESTRICTION', 'commons-booking-item-usage-restriction', 'deleted') . ' -' : date_i18n( get_option( 'date_format' ), strtotime($update['new_date_end'])),
                    'update_hint' => $update['update_hint'],
                    'created_at' => date_i18n( get_option( 'date_format' ), $update['created_at']->getTimestamp()),
                    'created_by_user' => [
                      'link' => get_edit_user_link( $update['created_by_user_id'] ),
                      'first_name' => $created_by_user->first_name,
                      'last_name' => $created_by_user->last_name
                    ],
                    'update_type' => isset($update['update_type']) ? $update['update_type'] : null
                  ];
                }
              }

              ?>

              <button class="cb-item-usage-restriction-show-details button action" title="<?= item_usage_restriction\__( 'LIST_RESTRICTION_DETAILS', 'commons-booking-item-usage-restriction', 'list details and changes ...') ?>"
                data-users='<?= json_encode($informed_user_items) ?>'
                data-coordinators='<?= json_encode(isset($item_restriction['coordinators']) ? $item_restriction['coordinators'] : []) ?>'
                data-emails='<?= json_encode($item_restriction['additional_emails']) ?>'
                data-updates='<?= json_encode($restriction_updates) ?>'
                data-hint='<?= $item_restriction['restriction_hint'] ?>'
                >

                <span style="padding-top: 4px;" class="dashicons dashicons-menu"></span>
              </button>

              <?php if(!$list_deleted_restrictions): ?>

                <button class="cb-item-usage-restriction-edit button action" title="<?= item_usage_restriction\__( 'EDIT_RESTRICTION', 'commons-booking-item-usage-restriction', 'edit ...') ?>"
                  data-item_id="<?= $item_restriction['item_id'] ?>"
                  data-created_at_timestamp="<?= $item_restriction['created_at']->getTimestamp() ?>"
                  data-created_by_user_id="<?= $item_restriction['created_by_user_id'] ?>"
                  data-date_start="<?= $item_restriction['date_start'] ?>"
                  data-date_end="<?= $item_restriction['date_end'] ?>"
                  data-hint="<?= $item_restriction['restriction_hint'] ?>">
                  <span style="padding-top: 4px;" class="dashicons dashicons-edit"></span>
                </button>

                <button class="cb-item-usage-restriction-delete button action" title="<?= item_usage_restriction\__( 'DELETE_RESTRICTION', 'commons-booking-item-usage-restriction', 'delete ...') ?>"
                  data-item_id="<?= $item_restriction['item_id'] ?>"
                  data-created_at_timestamp="<?= $item_restriction['created_at']->getTimestamp() ?>"
                  data-created_by_user_id="<?= $item_restriction['created_by_user_id'] ?>">
                  <span style="padding-top: 4px;" class="dashicons dashicons-trash"></span>
                </button>

              <?php endif; ?>

              <?php
                error_reporting(E_ALL);
                $chart_date_end = (new DateTime())->setTimestamp(strtotime($item_restriction['date_start'].'+ 2 months'));
                $chart_duration = $item_restriction['date_start_valid']->diff($chart_date_end);

                $origin = clone $item_restriction['date_start_valid'];
                $target = clone $item_restriction['date_end_valid'];
                $restriction_duration = $origin->diff($target);

                $scrollbar_x_end = ($restriction_duration->days + 1) / ($chart_duration->days);
                echo do_shortcode('[cb_bookings_gantt_chart item_id="' . $item_restriction['item_id'] . '" date_start="' . $item_restriction['date_start'] . '" date_end="' . $chart_date_end->format('Y-m-d') . '" scrollbar_x_end="' . $scrollbar_x_end . '"]');
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p><?= $list_deleted_restrictions ? item_usage_restriction\__( 'NO_DELETED_RESTRICTIONS_EXISTING', 'commons-booking-item-usage-restriction', 'There are no deleted restrictions for the chosen item.') :
                                          item_usage_restriction\__( 'NO_RESTRICTIONS_EXISTING', 'commons-booking-item-usage-restriction', 'There are no restrictions for the chosen item.') ?></p>
    <?php endif; ?>
  <?php else: ?>
    <p><?= item_usage_restriction\__( 'CHOOSE_ITEM', 'commons-booking-item-usage-restriction', 'Please choose an item to show restrictions.') ?></p>
  <?php endif; ?>

  <!-- edit restriction dialog form-->
  <div id="cb-item-usage-restriction-edit-dialog" class="hidden">

    <div style="overflow:auto">
      <p><?= item_usage_restriction\__( 'EDIT_RESTRICTION_DIALOG', 'commons-booking-item-usage-restriction', 'You can adjust the end date of the usage restriction. Affected users will be informed about that change.') ?></p>

      <form id="cb-item-usage-restriction-edit-form" method="POST">
        <input type="hidden" name="action" value="edit-restriction">
        <input type="hidden" name="item_id" value="">
        <input type="hidden" name="created_by_user_id" value="">
        <input type="hidden" name="created_at_timestamp" value="">

        <div style="margin-bottom: 1em;">
          <label for="date_start"><?= item_usage_restriction\__( 'FROM', 'commons-booking-item-usage-restriction', 'from') ?></label>
          <input type="date" name="date_start" disabled>
          <label for="date_end"><?= item_usage_restriction\__( 'UNTIL', 'commons-booking-item-usage-restriction', 'to') ?></label>
          <input type="date" name="date_end">
        </div>
        <label for="update_comment"><?= item_usage_restriction\__( 'UPDATE_COMMENT', 'commons-booking-item-usage-restriction', 'write a comment') ?>:</label><br>
        <textarea style="width: 100%; height: 100px;" name="update_comment" required></textarea><br>
        <button style="margin-top: 10px; float:right;" class="button action">
          <span style="padding-top: 4px;" class="dashicons dashicons-yes"></span> <?= item_usage_restriction\__( 'CONFIRM', 'commons-booking-item-usage-restriction', 'confirm') ?>
        </button>
      </form>
    </div>
    <hr>
    <div style="overflow:auto">
      <p><?= item_usage_restriction\__( 'REVISE_RESTRICTION_DIALOG', 'commons-booking-item-usage-restriction', 'You can revise the hint text of the usage restriction. That causes no email notifications.') ?></p>

      <form id="cb-item-usage-restriction-revise-form" method="POST">
        <input type="hidden" name="action" value="revise-restriction">
        <input type="hidden" name="item_id" value="">
        <input type="hidden" name="created_by_user_id" value="">
        <input type="hidden" name="created_at_timestamp" value="">

        <label for="restriction_hint"><?= item_usage_restriction\__( 'HINT', 'commons-booking-item-usage-restriction', 'hint') ?>:</label><br>
        <textarea style="width: 100%; height: 100px;" name="restriction_hint" required></textarea><br>

        <button style="margin-top: 10px; float:right;" class="button action">
          <span style="padding-top: 4px;" class="dashicons dashicons-yes"></span> <?= item_usage_restriction\__( 'CONFIRM', 'commons-booking-item-usage-restriction', 'confirm') ?>
        </button>
      </form>
    </div>
  </div>

  <!-- delete restriction dialog form-->
  <div id="cb-item-usage-restriction-delete-dialog" class="hidden">
    <p><?= item_usage_restriction\__( 'DELETE_RESTRICTION_DIALOG', 'commons-booking-item-usage-restriction', 'The restriction will be deleted and all users with running or outstanding bookings for the item in the restriction period will be informed.') ?></p>

    <form id="cb-item-usage-restriction-delete-form" method="POST">
      <input type="hidden" name="action" value="delete-restriction">
      <input type="hidden" name="item_id" value="">
      <input type="hidden" name="created_by_user_id" value="">
      <input type="hidden" name="created_at_timestamp" value="">
      <label for="delete_comment"><?= item_usage_restriction\__( 'DELETE_COMMENT', 'commons-booking-item-usage-restriction', 'write a comment') ?>:</label><br>
      <textarea style="width: 100%; height: 100px;" name="delete_comment" required></textarea><br>
      <button style="margin-top: 10px; float:right;" class="button action">
        <span style="padding-top: 4px;" class="dashicons dashicons-yes"></span> <?= item_usage_restriction\__( 'CONFIRM', 'commons-booking-item-usage-restriction', 'confirm') ?>
      </button>
    </form>
  </div>

  <!-- restriction details dialog -->
  <div id="cb-item-usage-restriction-details-dialog" class="hidden">
    <p><?= item_usage_restriction\__( 'RESTRICTION_DETAILS_DIALOG', 'commons-booking-item-usage-restriction', 'details for the chosen restriction about informed users and changes.') ?></p>

    <h2><?= item_usage_restriction\__( 'NOTIFICATIONS', 'commons-booking-item-usage-restriction', 'notifications') ?></h2>
    <table id="informed-users" class="wp-list-table" style="width: 100%; margin-top: 20px;">
      <thead>
        <tr>
          <th style="width: 34%"><?= item_usage_restriction\__( 'TO_USERS', 'commons-booking-item-usage-restriction', 'to users') ?></th>
          <th style="width: 33%"><?= item_usage_restriction\__( 'TO_COORDINATORS', 'commons-booking-item-usage-restriction', 'to coordinators') ?></th>
          <th style="width: 33%"><?= item_usage_restriction\__( 'ADDITIONAL_EMAILS', 'commons-booking-item-usage-restriction', 'additional emails') ?></th>
        </tr>
      </thead>
      <tbody><tr></tr></tbody>
    </table>

    <h2><?= item_usage_restriction\__( 'HINT', 'commons-booking-item-usage-restriction', 'hint') ?></h2>
    <div id="restriction-hint"></div>

    <h2><?= item_usage_restriction\__( 'RESTRICTION_DATE_CHANGES', 'commons-booking-item-usage-restriction', 'history of changes') ?></h2>
    <table id="restriction-changes" class="wp-list-table" style="width: 100%; margin-top: 20px;">
      <thead>
        <tr>
          <th style="width: 15%"><?= item_usage_restriction\__( 'OLD_END_DATE', 'commons-booking-item-usage-restriction', 'old end date') ?></th>
          <th style="width: 15%"><?= item_usage_restriction\__( 'NEW_END_DATE', 'commons-booking-item-usage-restriction', 'new end date') ?></th>
          <th style="width: 15%"><?= item_usage_restriction\__( 'CREATED_BY', 'commons-booking-item-usage-restriction', 'created by') ?></th>
          <th style="width: 15%"><?= item_usage_restriction\__( 'CREATED_AT', 'commons-booking-item-usage-restriction', 'created at') ?></th>
          <th style="width: 40%"><?= item_usage_restriction\__( 'EDIT_HINT', 'commons-booking-item-usage-restriction', 'edit hint') ?></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div id="no-restriction-changes" style="width: 100%; text-align: center;">- <?= item_usage_restriction\__( 'NO_CHANGES', 'commons-booking-item-usage-restriction', 'no changes') ?> -</div>
  </div>

  <script>


    jQuery(document).ready(function ($) {
      //helper div for correct positioning of dialogs
      var $overlay = $('<div id="positioning-overlay" style="position: fixed; top: 0; left: 0; bottom: 0; right: 0; display: none;"></div>');
      $('body').append($overlay);

      function initialize_restriction_action_dialog(button_class, dialog_wrapper_id, dialog_width, dialog_title, dialog_form, input_field_names, open_callback) {
        // initialize the dialog
        $('#' + dialog_wrapper_id).dialog({
          title: dialog_title,
          dialogClass: 'wp-dialog',
          autoOpen: false,
          draggable: false,
          width: dialog_width,
          modal: true,
          resizable: false,
          closeOnEscape: true,
          position: {
            my: "top",
            at: "top+10%",
            of: '#positioning-overlay'
          },
          open: function (event) {
            // close dialog by clicking the overlay behind it
            $('.ui-widget-overlay').bind('click', function(){
              $('#' + dialog_wrapper_id).dialog('close');
            })

            // hide close button, because of a styling issue
            $(".ui-dialog-titlebar-close").hide();

            var data = $(this).data();
            //console.log(data);

            if(dialog_form) {
              if(typeof dialog_form == 'string') {
                dialog_form_id = [dialog_form];
              }

              dialog_form.forEach(function(dialog_form_id) {
                var $form = $('#' + dialog_form_id);
                //set values for given field names based on data object
                $.each(input_field_names, function(index, field_name) {
                  $form.find('input[name="' + field_name + '"]').val(data[field_name]);
                });

                $form.submit(function() {
                  $(this).attr("disabled","disabled");
                })
              });
            }

            typeof open_callback == 'function' && open_callback(data);
          },
          close: function() {
            $overlay.hide();
          },
          create: function () {

          },
        }).parent().css({position:"fixed"});
        // bind a button or a link to open the dialog
        $('.' + button_class).click(function(e) {
          e.preventDefault();

          var data = $(this).data();
          var $dialog = $('#' + dialog_wrapper_id);
          $dialog.data(data);

          $overlay.css('left', $('#wpcontent').css('margin-left'));
          $overlay.show();

          $dialog.dialog('open');
        });
      }

      initialize_restriction_action_dialog(
        'cb-item-usage-restriction-edit',
        'cb-item-usage-restriction-edit-dialog',
        600,
        '<?= item_usage_restriction\__( 'EDIT_RESTRICTION_DIALOG_TITLE','commons-booking-item-usage-restriction', 'Edit Restriction') ?>',
        ['cb-item-usage-restriction-edit-form', 'cb-item-usage-restriction-revise-form'],
        ['item_id', 'created_by_user_id', 'created_at_timestamp', 'date_start', 'date_end', 'hint'],
        function(data) {
          var $form1 = $('#cb-item-usage-restriction-edit-form');
          $form1.find('input[name="date_end"]').attr('min', data['date_start']);

          var $form2 = $('#cb-item-usage-restriction-revise-form');
          $form2.find('textarea[name="restriction_hint"]').text(data.hint);
        });

      initialize_restriction_action_dialog(
        'cb-item-usage-restriction-delete',
        'cb-item-usage-restriction-delete-dialog',
        600,
        '<?= item_usage_restriction\__( 'DELETE_RESTRICTION_DIALOG_TITLE','commons-booking-item-usage-restriction', 'Delete Restriction') ?>',
        'cb-item-usage-restriction-delete-form',
        ['item_id', 'created_by_user_id', 'created_at_timestamp']);

      initialize_restriction_action_dialog(
        'cb-item-usage-restriction-show-details',
        'cb-item-usage-restriction-details-dialog',
        800,
        '<?= item_usage_restriction\__( 'RESTRICTION_DETAILS_DIALOG_TITLE','commons-booking-item-usage-restriction', 'Restriction Details') ?>',
        null,
        null,
        function(data) {

          var $tr = $('#informed-users > tbody > tr').first();
          $tr.html('');

          var $td;

          //users
          $td = $('<td valign="top"></td>');
          html = '';
          for(var i = 0; i < data.users.length; i++) {
            var user = data.users[i];
            html += i > 0 ? ',&nbsp;' : '';
            html += '<a href="' + user.link + '">' + user.first_name + ' ' + user.last_name + '</a>';
          }
          $td.html(html);
          $tr.append($td);

          //coordinators
          $td = $('<td valign="top"></td>');
          html = '';
          console.log('data.coordinators: ', data.coordinators);
          if(data.coordinators) {
            data.coordinators.forEach(function(coordinator, i) {
              html += i > 0 ? ',&nbsp;' : '';
              html += coordinator.first_name + ' &gt; ' + coordinator.user_email
            });
          }
          else {
            html = '- <?= item_usage_restriction\__( 'COORDINATORS_NOT_LOGGED','commons-booking-item-usage-restriction', 'coordinators not logged') ?> -';
          }
          $td.html(html);
          $tr.append($td);

          //emails
          $td = $('<td valign="top"></td>');
          $td.html(data.emails.join(', '));
          $tr.append($td);

          //hint
          $('#restriction-hint').text(data.hint);

          //updates
          var $tbody = $('#restriction-changes > tbody').first();
          $tbody.html('');
          if(data.updates.length > 0) {
            $('#restriction-changes').show();
            $('#no-restriction-changes').hide();

            for(var i = 0; i < data.updates.length; i++) {
              var update = data.updates[i];

              $tr = $("<tr><td>" + update.old_date_end + "</td><td>" + update.new_date_end + "</td><td><a href='" + update.created_by_user.link + "'>" +update.created_by_user.first_name + " " +update.created_by_user.last_name + " </a></td><td>" + update.created_at + "</td><td>" + update.update_hint + "</td></tr>");
              if(update.update_type == 'delete') {
                $tr.css('background-color', '#ccc');
              }
              $tbody.append($tr);
            }
          }
          else {
            $('#restriction-changes').hide();
            $('#no-restriction-changes').show();
          }

        });

    });
  </script>

<?php endif; ?>
