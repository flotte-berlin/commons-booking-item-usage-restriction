
<?php if(count($restrictions) > 0): ?>
  <div class="cb-item-usage-restrictions">
    <h3>
      <?= item_usage_restriction\__( 'USAGE_RESTRICTIONS', 'commons-booking-item-usage-restriction', "Usage Restrictions") ?>
    </h3>
    <ul>
    <?php foreach ($restrictions as $restriction): ?>
      <li class="cb-item-usage-restriction">
        <span class="cb-item-usage-restriction-date"><?= date_i18n( get_option( 'date_format' ), strtotime($restriction['date_start']) )?> - <?= date_i18n( get_option( 'date_format' ), strtotime($restriction['date_end']) )?></span>
        (<?= $restriction['restriction_type'] == 1 ? item_usage_restriction\__( 'RESTRICTION_TYPE_1', 'commons-booking-item-usage-restriction', 'total breakdown') : item_usage_restriction\__( 'RESTRICTION_TYPE_2', 'commons-booking-item-usage-restriction', 'usable to a limited extend') ?>):<br>
        <?= $restriction['restriction_hint'] ?>

        <?php if($show_update_hints && isset($restriction['updates'])): ?>
          <ul>
            <?php foreach ($restriction['updates'] as $update): ?>
              <li class="cb-item-usage-restriction-update">
              <span class="cb-item-usage-restriction-update-date"><?= date_i18n( get_option( 'date_format' ), $update['created_at']->getTimestamp()) ?>:</span> <?= $update['update_hint'] ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

      </li>
    <?php endforeach; ?>
  </ul>
  </div>
<?php else: ?>
    <?php if($appears_always): ?>
      <div class="cb-item-no-usage-restrictions">
        <h3>
          <?= item_usage_restriction\__( 'USAGE_RESTRICTIONS', 'commons-booking-item-usage-restriction', "Usage Restrictions") ?>
        </h3>
        <ul>
          <li><?= item_usage_restriction\__( 'NO_USAGE_RESTRICTIONS', 'commons-booking-item-usage-restriction', "none") ?></li>
        </ul>
      </div>
    <?php endif; ?>
<?php endif; ?>
<br>
