<?php
use Vitaliq\Services\LaboratoryService;

$s = $sample;
$stages = ['awaiting_collection', 'collected', 'received', 'in_analysis', 'stored'];
$reached = array_column($events, 'to_status');
$actions = ['collected' => 'Mark collected', 'in_transit' => 'Send to laboratory', 'received' => 'Receive at laboratory', 'in_analysis' => 'Start analysis', 'stored' => 'Store', 'disposed' => 'Dispose'];
?>
<div class="app-head">
  <div>
    <h1>Sample <span class="ref"><?= e($s['barcode']) ?></span></h1>
    <p><?= e($s['full_name']) ?> · <?= e(LaboratoryService::SPECIMENS[$s['specimen']]) ?> · order <a href="<?= e(url('/lab/orders/' . (int) $s['order_id'])) ?>"><?= e($s['order_no']) ?></a></p>
  </div>
  <div class="cluster">
    <span class="status status--<?= e($s['status']) ?>"><?= e(LaboratoryService::SAMPLE_STATUSES[$s['status']]) ?></span>
    <a class="btn btn--secondary" href="<?= e(url('/lab/samples/' . (int) $s['id'] . '/label')) ?>" target="_blank" rel="noopener">Print label</a>
  </div>
</div>

<ol class="path" aria-label="Sample path">
  <?php foreach ($stages as $st): ?>
    <li class="<?= in_array($st, $reached, true) ? 'done' : '' ?><?= $s['status'] === $st ? ' now' : '' ?>"><?= e(LaboratoryService::SAMPLE_STATUSES[$st]) ?></li>
  <?php endforeach; ?>
  <?php if ($s['status'] === 'rejected'): ?><li class="stop">Rejected</li><?php endif; ?>
</ol>

<div class="vq-grid">
  <section class="panel span-7" aria-labelledby="ev-title">
    <div class="panel__head"><h2 id="ev-title">History</h2></div>
    <ol class="timeline">
      <?php foreach (array_reverse($events) as $ev): ?>
        <li><time datetime="<?= e(iso_datetime($ev['created_at'])) ?>"><?= e(fmt_datetime($ev['created_at'])) ?></time>
          <strong><?= e(LaboratoryService::SAMPLE_STATUSES[$ev['to_status']] ?? $ev['to_status']) ?></strong>
          <div class="who"><?= e($ev['full_name'] ?? 'System') ?><?= $ev['note'] ? ' · ' . e($ev['note']) : '' ?></div></li>
      <?php endforeach; ?>
    </ol>
  </section>
  <div class="span-5 stack">
    <?php if ($s['status'] === 'rejected'): ?>
      <section class="panel">
        <div class="panel__head"><h2>Rejected</h2></div>
        <p><?= e(LaboratoryService::REJECTION_REASONS[$s['rejection_reason']] ?? '') ?><?= $s['rejection_note'] ? ': ' . e($s['rejection_note']) : '' ?></p>
        <?php if ($replacement !== null): ?>
          <a href="<?= e(url('/lab/samples/' . (int) $replacement)) ?>">Open the replacement sample</a>
        <?php else: ?>
          <form method="post" action="<?= e(url('/lab/samples/' . (int) $s['id'] . '/recollect')) ?>"><?= csrf_field() ?><button class="btn btn--primary" type="submit">Request a new sample</button></form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <?php $moves = array_values(array_filter($next, static fn ($n) => $n !== 'rejected')); ?>
    <?php if ($moves !== []): ?>
      <section class="panel">
        <div class="panel__head"><h2>Next step</h2></div>
        <?php foreach ($moves as $to): ?>
          <form method="post" action="<?= e(url('/lab/samples/' . (int) $s['id'] . '/move')) ?>" class="mt-4">
            <?= csrf_field() ?><input type="hidden" name="to" value="<?= e($to) ?>">
            <?php if ($to === 'stored'): ?>
              <div class="field"><label for="f-loc">Storage location</label><input class="input" id="f-loc" name="storage_location" maxlength="40" placeholder="Fridge 2 / Rack B4" required></div>
            <?php endif; ?>
            <button class="btn <?= $to === $moves[0] ? 'btn--primary' : 'btn--secondary' ?> mt-4" type="submit"><?= e($actions[$to] ?? ucfirst($to)) ?></button>
          </form>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
    <?php if (in_array('rejected', $next, true)): ?>
      <details class="confirm confirm--danger">
        <summary>Reject this sample</summary>
        <div class="confirm__body">
          <form method="post" action="<?= e(url('/lab/samples/' . (int) $s['id'] . '/move')) ?>">
            <?= csrf_field() ?><input type="hidden" name="to" value="rejected">
            <div class="field"><label for="f-rr">Reason</label>
              <select class="input" id="f-rr" name="rejection_reason" required><option value="">Choose</option>
                <?php foreach (LaboratoryService::REJECTION_REASONS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
              </select></div>
            <div class="field mt-4"><label for="f-rn">Note <span class="optional">required for "Other"</span></label><input class="input" id="f-rn" name="rejection_note" maxlength="300"></div>
            <button class="btn btn--danger mt-4" type="submit">Reject sample</button>
          </form>
        </div>
      </details>
    <?php endif; ?>
    <?php if ($s['storage_location']): ?><p class="hint">Stored at <?= e($s['storage_location']) ?>.</p><?php endif; ?>
  </div>
</div>
