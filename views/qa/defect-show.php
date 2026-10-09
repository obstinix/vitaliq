<?php
use Vitaliq\Support\Labels;

$d = $defect;
?>
<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/qa/defects')) ?>">Defect register</a></p>
    <h1><span class="mono"><?= e($d['code']) ?></span> <?= e($d['title']) ?></h1>
    <p>
      <span class="status status--<?= e($d['status']) ?>"><?= e(Labels::get(Labels::DEFECT_STATUSES, $d['status'])) ?></span>
      · <?= e(Labels::get(Labels::SEVERITIES, $d['severity'])) ?> severity · <?= e(Labels::get(Labels::PRIORITIES, $d['priority'])) ?> priority
      · <span class="tag<?= $d['origin'] === 'demo' ? ' tag--demo' : '' ?>"><?= e(Labels::get(Labels::DEFECT_ORIGINS, $d['origin'])) ?></span>
    </p>
  </div>
</div>
<div class="grid-2">
  <article class="panel prose">
    <dl class="details mb-5">
      <dt>Module</dt><dd><?= e($d['module']) ?></dd>
      <dt>Created</dt><dd><?= e(fmt_datetime($d['created_at'])) ?><?= $d['reporter'] ? ' by ' . e($d['reporter']) : '' ?></dd>
      <?php if ($d['resolved_at']): ?><dt>Resolved</dt><dd><?= e(fmt_datetime($d['resolved_at'])) ?></dd><?php endif; ?>
    </dl>
    <h2 class="h3-like">Description</h2><p class="comment-body"><?= e($d['description']) ?></p>
    <h2 class="h3-like">Steps to reproduce</h2><p class="comment-body"><?= e($d['steps_to_reproduce']) ?></p>
    <h2 class="h3-like">Expected behaviour</h2><p class="comment-body"><?= e($d['expected_behaviour']) ?></p>
    <h2 class="h3-like">Actual behaviour</h2><p class="comment-body"><?= e($d['actual_behaviour']) ?></p>
    <?php if ($d['resolution_notes']): ?><h2 class="h3-like">Resolution</h2><p class="comment-body"><?= e($d['resolution_notes']) ?></p><?php endif; ?>
  </article>
  <div class="stack">
    <section class="panel" aria-labelledby="upd-title">
      <div class="panel__head"><h2 id="upd-title">Update status</h2></div>
      <?= $view->partial('error-summary', ['errors' => $errors]) ?>
      <form class="stack" method="post" action="<?= e(url('/qa/defects/' . (int) $d['id'])) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="f-status">Status</label>
          <select class="input" id="f-status" name="status"><?php foreach (Labels::DEFECT_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($d['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field">
          <label for="f-resolution_notes">Resolution notes <span class="optional">(required to resolve or close)</span></label>
          <textarea class="input" id="f-resolution_notes" name="resolution_notes" rows="3" maxlength="2000"<?= aria_invalid($errors, 'resolution_notes') ?>></textarea>
          <?= field_error($errors, 'resolution_notes') ?>
        </div>
        <button class="btn btn--primary btn--small" type="submit">Save</button>
      </form>
    </section>
    <section class="panel" aria-labelledby="cases-title">
      <div class="panel__head"><h2 id="cases-title">Linked test cases</h2></div>
      <?php if ($d['cases'] === []): ?>
        <p class="muted mb-0">No test cases are linked. Link one from a test case page.</p>
      <?php else: ?>
        <ul class="feed">
          <?php foreach ($d['cases'] as $c): ?>
            <li><a class="mono small" href="<?= e(url('/qa/cases/' . (int) $c['id'])) ?>"><?= e($c['case_code']) ?></a><span><?= e($c['title']) ?> <span class="muted">· <?= e($c['module']) ?></span></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
