<?php
use Vitaliq\Services\LabResourcesService;
?>
<div class="app-head">
  <div>
    <h1><?= e($eq['name']) ?></h1>
    <p><span class="ref"><?= e($eq['asset_tag']) ?></span><?= $eq['model'] ? ' · ' . e(trim(($eq['manufacturer'] ?? '') . ' ' . $eq['model'])) : '' ?><?= $eq['serial_no'] ? ' · S/N ' . e($eq['serial_no']) : '' ?> · calibrated every <?= (int) $eq['calibration_interval_days'] ?> days</p>
  </div>
  <span class="status status--<?= e($eq['status'] === 'operational' ? 'ok' : 'out_of_service') ?>"><?= e(LabResourcesService::EQUIPMENT_STATUSES[$eq['status']]) ?></span>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<section class="kpis" aria-label="Calibration">
  <div class="kpi"><span class="kpi__value"><?= $eq['last_calibrated_on'] ? e($eq['last_calibrated_on']) : '<span class="faint">—</span>' ?></span><span class="kpi__label">Last calibrated</span></div>
  <div class="kpi<?= $eq['calibration_due_on'] !== null && $eq['calibration_due_on'] < $today ? ' kpi--alert' : '' ?>"><span class="kpi__value"><?= $eq['calibration_due_on'] ? e($eq['calibration_due_on']) : '<span class="faint">—</span>' ?></span><span class="kpi__label">Calibration due</span></div>
  <div class="kpi"><span class="kpi__value"><?= count($eq['logs']) ?></span><span class="kpi__label">Log entries</span></div>
</section>
<div class="vq-grid">
  <section class="panel span-7" aria-labelledby="log">
    <div class="panel__head"><h2 id="log">Logbook</h2></div>
    <?php if ($eq['logs'] === []): ?>
      <p class="empty">No entries yet.</p>
    <?php else: ?>
      <ol class="timeline">
        <?php foreach ($eq['logs'] as $l): ?>
          <li><time datetime="<?= e($l['performed_on']) ?>"><?= e($l['performed_on']) ?></time><strong><?= e(LabResourcesService::LOG_KINDS[$l['kind']]) ?></strong>
            <div class="who"><?= e($l['full_name'] ?? '—') ?><?= $l['notes'] ? ' · ' . e($l['notes']) : '' ?></div></li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
  <section class="panel span-5" aria-labelledby="add-log">
    <div class="panel__head"><h2 id="add-log">Add an entry</h2></div>
    <form method="post" action="<?= e(url('/lab/equipment/' . (int) $eq['id'] . '/log')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label for="f-kind">Entry</label><select class="input" id="f-kind" name="kind"<?= aria_invalid($errors, 'kind') ?>><?php foreach (LabResourcesService::LOG_KINDS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'kind'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'kind') ?></div>
        <div class="field"><label for="f-status">New status <span class="optional">for a status change</span></label><select class="input" id="f-status" name="status"><option value="">—</option><?php foreach (LabResourcesService::EQUIPMENT_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'status') ?></div>
        <div class="field"><label for="f-performed_on">Date</label><input class="input" type="date" id="f-performed_on" name="performed_on" max="<?= e($today) ?>" value="<?= e(old($old, 'performed_on', $today)) ?>"<?= aria_invalid($errors, 'performed_on') ?>><?= field_error($errors, 'performed_on') ?></div>
        <div class="field"><label for="f-notes">Notes</label><textarea class="input" id="f-notes" name="notes" rows="3" maxlength="500"<?= aria_invalid($errors, 'notes') ?>><?= e(old($old, 'notes')) ?></textarea><?= field_error($errors, 'notes') ?></div>
      </div>
      <p class="hint mt-4">A QC failure takes the instrument out of service until a calibration or status change is recorded.</p>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Save entry</button></div>
    </form>
  </section>
</div>
