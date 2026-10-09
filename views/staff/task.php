<?php use Vitaliq\Support\Labels; ?>
<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/staff')) ?>">Your collections</a></p>
    <h1><span class="ref"><?= e($request['reference']) ?></span></h1>
    <p><span class="status status--<?= e($request['status']) ?>"><?= e(status_label($request['status'])) ?></span></p>
  </div>
</div>
<div class="grid-2">
  <section class="panel" aria-labelledby="visit-title">
    <div class="panel__head"><h2 id="visit-title">Visit</h2></div>
    <dl class="details">
      <dt>Patient</dt><dd><?= e($request['full_name']) ?></dd>
      <dt>Mobile</dt><dd class="mono"><?= e(format_mobile($request['mobile'])) ?></dd>
      <dt>Test</dt><dd><?= e(Labels::get(Labels::TEST_TYPES, $request['test_type'])) ?></dd>
      <dt>Where</dt><dd><?= e(Labels::get(Labels::COLLECTION_MODES, $request['collection_mode'])) ?><?= $request['collection_address'] ? ': ' . e($request['collection_address']) : '' ?>, <?= e($request['city']) ?> <?= e($request['pincode']) ?></dd>
      <dt>When</dt><dd><?= e(fmt_date($request['preferred_date'], 'l j M')) ?> · <?= e(Labels::get(Labels::SLOTS, $request['preferred_slot'])) ?></dd>
      <?php if ($request['notes']): ?><dt>Notes</dt><dd><?= e($request['notes']) ?></dd><?php endif; ?>
    </dl>
  </section>
  <section class="panel" aria-labelledby="collect-title">
    <div class="panel__head"><h2 id="collect-title">Record collection</h2></div>
    <?php if ($request['status'] !== 'assigned'): ?>
      <p class="muted mb-0">Collection has been recorded for this request.</p>
    <?php else: ?>
      <?= $view->partial('collect-form', ['action' => url('/staff/tasks/' . (int) $request['id'] . '/collect'), 'errors' => $errors, 'old' => $old]) ?>
    <?php endif; ?>
  </section>
</div>
