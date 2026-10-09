<?php
/**
 * Progress through the normal workflow for one request.
 * Expects $request (with status and history).
 */
use Vitaliq\Services\TestingService;
use Vitaliq\Support\Labels;

$status = $request['status'];
$stages = TestingService::STAGES;
$position = array_search($status, $stages, true);
$reached = [];
foreach ($request['history'] ?? [] as $h) {
    $reached[$h['to_status']] ??= $h['created_at'];
}
?>
<figure class="rail-figure">
  <figcaption><span>Progress</span><span><?= e(status_label($status)) ?></span></figcaption>
  <?php if ($position === false): ?>
    <p class="mb-0"><span class="status status--<?= e($status) ?>"><?= e(status_label($status)) ?></span></p>
    <?php if (!empty($request['closed_reason'])): ?><p class="mt-4 mb-0 muted"><?= e($request['closed_reason']) ?></p><?php endif; ?>
  <?php else: ?>
    <ol class="rail">
      <?php foreach ($stages as $i => $stage): ?>
        <?php $state = $i < $position ? 'is-done' : ($i === $position ? ($stage === 'result_available' ? 'is-done' : 'is-current') : 'is-pending'); ?>
        <li class="<?= $state ?>">
          <span class="rail__node" aria-hidden="true"></span>
          <span class="rail__label"><?= e(Labels::STATUSES[$stage]) ?><span class="visually-hidden"> — <?= $state === 'is-done' ? 'done' : ($state === 'is-current' ? 'current step' : 'not yet') ?></span></span>
          <span class="rail__meta"><?= isset($reached[$stage]) ? e(fmt_date($reached[$stage], 'j M, g:i a')) : '' ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <p class="hint mt-4 mb-0"><?= e(Labels::STATUS_HELP[$status]) ?></p>
  <?php endif; ?>
</figure>
