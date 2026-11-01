<?php
$i = $imp;
?>
<div class="app-head">
  <div>
    <h1><?= e($i['filename']) ?></h1>
    <p><?= e($i['source_name']) ?> · <?= fmt_number($i['rows_total']) ?> rows, <?= fmt_number($i['rows_ok']) ?> valid, <?= fmt_number($i['rows_failed']) ?> rejected · uploaded <?= e(fmt_datetime($i['created_at'])) ?> by <?= e($i['full_name'] ?? '—') ?></p>
  </div>
  <div class="cluster">
    <?php if ($i['status'] === 'previewed'): ?>
      <form method="post" action="<?= e(url('/surveillance/imports/' . (int) $i['id'])) ?>"><?= csrf_field() ?><button class="btn btn--secondary" name="action" value="discard" type="submit">Discard</button></form>
      <?php if ((int) $i['rows_ok'] > 0): ?>
        <form method="post" action="<?= e(url('/surveillance/imports/' . (int) $i['id'])) ?>"><?= csrf_field() ?><button class="btn btn--primary" name="action" value="commit" type="submit">Import <?= fmt_number($i['rows_ok']) ?> valid rows</button></form>
      <?php endif; ?>
    <?php elseif ($i['status'] === 'committed'): ?>
      <form method="post" action="<?= e(url('/surveillance/imports/' . (int) $i['id'])) ?>"><?= csrf_field() ?><button class="btn btn--danger" name="action" value="rollback" type="submit">Roll back this import</button></form>
    <?php endif; ?>
  </div>
</div>
<p class="hint">SHA-256 <span class="ref"><?= e(substr((string) $i['sha256'], 0, 16)) ?>…</span> · status <?= e(str_replace('_', ' ', (string) $i['status'])) ?><?= $i['committed_at'] ? ' on ' . e(fmt_datetime($i['committed_at'])) : '' ?></p>
<div class="vq-grid">
  <section class="panel span-6" aria-labelledby="errs">
    <div class="panel__head"><h2 id="errs">Rejected rows</h2><span class="meta">First 200</span></div>
    <?php if ($i['errors'] === []): ?>
      <p class="empty">Every row passed the checks.</p>
    <?php else: ?>
      <table class="table"><thead><tr><th scope="col" class="num">Row</th><th scope="col">Problem</th></tr></thead><tbody>
        <?php foreach ($i['errors'] as $er): ?><tr><td class="num"><?= (int) $er['row'] ?></td><td><?= e($er['error']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </section>
  <section class="panel span-6" aria-labelledby="sample">
    <div class="panel__head"><h2 id="sample">First valid rows</h2></div>
    <?php if ($i['sample'] === []): ?>
      <p class="empty"><?= $i['status'] === 'previewed' ? 'No valid rows.' : 'Shown only before the rows are imported.' ?></p>
    <?php else: ?>
      <table class="table"><thead><tr><th scope="col">Disease</th><th scope="col">Date</th><th scope="col">State</th><th scope="col" class="num">Tests</th><th scope="col" class="num">Positive</th></tr></thead><tbody>
        <?php foreach ($i['sample'] as $r): ?><tr><td><?= e($diseases[$r['disease']] ?? $r['disease']) ?></td><td class="num"><?= e($r['period_start']) ?></td><td><?= e($states[$r['state_code']] ?? $r['state_code']) ?></td><td class="num"><?= fmt_number($r['tests']) ?></td><td class="num"><?= fmt_number($r['positives']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </section>
</div>
