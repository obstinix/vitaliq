<?php use Vitaliq\Support\Labels; ?>
<div class="app-head">
  <div>
    <p class="eyebrow">Phlebotomist</p>
    <h1>Your collections</h1>
    <p><?= count($open) === 0 ? 'No collections are waiting for you.' : count($open) . ' collection' . (count($open) === 1 ? '' : 's') . ' waiting, ordered by preferred date and slot.' ?></p>
  </div>
</div>

<section aria-labelledby="open-title">
  <h2 id="open-title" class="visually-hidden">Open collections</h2>
  <?php if ($open === []): ?>
    <div class="empty"><h3>All clear</h3><p>New assignments appear here and on the notification bell.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead><tr><th scope="col">When</th><th scope="col">Reference</th><th scope="col">Patient</th><th scope="col">Where</th><th scope="col">Test</th><th scope="col"><span class="visually-hidden">Action</span></th></tr></thead>
        <tbody>
          <?php foreach ($open as $t): ?>
            <tr>
              <td data-label="When"><strong><?= e(fmt_date($t['preferred_date'], 'D j M')) ?></strong><br><span class="muted small"><?= e(Labels::get(Labels::SLOTS, $t['preferred_slot'])) ?></span></td>
              <td data-label="Reference"><span class="ref"><?= e($t['reference']) ?></span></td>
              <td data-label="Patient"><?= e($t['patient_name']) ?><br><span class="mono small"><?= e(format_mobile($t['patient_mobile'])) ?></span></td>
              <td data-label="Where"><?= e(Labels::get(Labels::COLLECTION_MODES, $t['collection_mode'])) ?><br><span class="muted small"><?= e(($t['collection_address'] ? $t['collection_address'] . ', ' : '') . $t['city'] . ' ' . $t['pincode']) ?></span></td>
              <td data-label="Test"><?= e(Labels::get(Labels::TEST_TYPES_SHORT, $t['test_type'])) ?></td>
              <td data-label="Action"><a class="btn btn--primary btn--small" href="<?= e(url('/staff/tasks/' . (int) $t['id'])) ?>">Record collection</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="mt-6" aria-labelledby="done-title">
  <h2 id="done-title" class="h3-like">Recently collected</h2>
  <?php if ($done === []): ?>
    <p class="muted">Collections you record will be listed here.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead><tr><th scope="col">Reference</th><th scope="col">Collected</th><th scope="col">Current status</th></tr></thead>
        <tbody>
          <?php foreach ($done as $t): ?>
            <tr>
              <td data-label="Reference"><span class="ref"><?= e($t['reference']) ?></span></td>
              <td data-label="Collected"><?= e(fmt_datetime($t['collected_at'])) ?></td>
              <td data-label="Status"><span class="status status--<?= e($t['status']) ?>"><?= e(status_label($t['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
