<?php
use Vitaliq\Services\LabResourcesService;

$cal = ['overdue' => ['Overdue', 'critical'], 'due' => ['Due within 14 days', 'warning'], 'ok' => ['In date', 'ok'], 'unknown' => ['Not recorded', 'pending']];
$overdue = count(array_filter($rows, static fn ($r) => $r['calibration_state'] === 'overdue'));
$down = count(array_filter($rows, static fn ($r) => $r['status'] !== 'operational'));
?>
<div class="app-head">
  <div>
    <h1>Equipment</h1>
    <p><?= count($rows) ?> instruments · <?= $down ?> not in use · <?= $overdue ?> overdue for calibration.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="span-8">
    <?php if ($rows === []): ?>
      <p class="empty">No equipment registered yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Instrument</th><th scope="col">Status</th><th scope="col" class="num">Calibration due</th><th scope="col">Calibration</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td data-label="Instrument"><a href="<?= e(url('/lab/equipment/' . (int) $r['id'])) ?>"><?= e($r['name']) ?></a><span class="sub"><span class="ref"><?= e($r['asset_tag']) ?></span><?= $r['model'] ? ' · ' . e(trim(($r['manufacturer'] ?? '') . ' ' . $r['model'])) : '' ?></span></td>
                <td data-label="Status"><span class="status status--<?= e($r['status'] === 'operational' ? 'ok' : ($r['status'] === 'maintenance' ? 'warning' : 'out_of_service')) ?>"><?= e(LabResourcesService::EQUIPMENT_STATUSES[$r['status']]) ?></span></td>
                <td data-label="Calibration due" class="num"><?= $r['calibration_due_on'] ? e($r['calibration_due_on']) : '—' ?></td>
                <td data-label="Calibration"><span class="status status--<?= e($cal[$r['calibration_state']][1]) ?>"><?= e($cal[$r['calibration_state']][0]) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <section class="panel span-4" aria-labelledby="add-eq">
    <div class="panel__head"><h2 id="add-eq">Register equipment</h2></div>
    <form method="post" action="<?= e(url('/lab/equipment')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label for="f-asset_tag">Asset tag</label><input class="input input--mono" id="f-asset_tag" name="asset_tag" maxlength="20" value="<?= e(old($old, 'asset_tag')) ?>"<?= aria_invalid($errors, 'asset_tag') ?>><?= field_error($errors, 'asset_tag') ?></div>
        <div class="field"><label for="f-name">Name</label><input class="input" id="f-name" name="name" maxlength="120" value="<?= e(old($old, 'name')) ?>"<?= aria_invalid($errors, 'name') ?>><?= field_error($errors, 'name') ?></div>
        <div class="field"><label for="f-manufacturer">Manufacturer <span class="optional">optional</span></label><input class="input" id="f-manufacturer" name="manufacturer" maxlength="80" value="<?= e(old($old, 'manufacturer')) ?>"></div>
        <div class="field"><label for="f-model">Model <span class="optional">optional</span></label><input class="input" id="f-model" name="model" maxlength="80" value="<?= e(old($old, 'model')) ?>"></div>
        <div class="field"><label for="f-serial_no">Serial number <span class="optional">optional</span></label><input class="input" id="f-serial_no" name="serial_no" maxlength="60" value="<?= e(old($old, 'serial_no')) ?>"></div>
        <div class="field"><label for="f-calibration_interval_days">Calibrate every (days)</label><input class="input" id="f-calibration_interval_days" name="calibration_interval_days" inputmode="numeric" value="<?= e(old($old, 'calibration_interval_days', '180')) ?>"<?= aria_invalid($errors, 'calibration_interval_days') ?>><?= field_error($errors, 'calibration_interval_days') ?></div>
        <div class="field"><label for="f-last_calibrated_on">Last calibrated <span class="optional">optional</span></label><input class="input" type="date" id="f-last_calibrated_on" name="last_calibrated_on" max="<?= e($today) ?>" value="<?= e(old($old, 'last_calibrated_on')) ?>"<?= aria_invalid($errors, 'last_calibrated_on') ?>><?= field_error($errors, 'last_calibrated_on') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Register</button></div>
    </form>
  </section>
</div>
