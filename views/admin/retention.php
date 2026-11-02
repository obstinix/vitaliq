<?php
$waiting = count(array_filter($requests, static fn ($r) => $r['status'] === 'requested'));
?>
<div class="app-head">
  <div>
    <h1>Data retention</h1>
    <p><?= $waiting ?> deletion <?= $waiting === 1 ? 'request is' : 'requests are' ?> waiting. Clinical records are never removed by retention: how long they are kept is a legal decision for the operator.</p>
  </div>
  <form method="post" action="<?= e(url('/admin/retention/purge')) ?>"><?= csrf_field() ?><button class="btn btn--secondary" type="submit">Remove expired records now</button></form>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="panel span-5" aria-labelledby="pol">
    <div class="panel__head"><h2 id="pol">Keep for</h2><span class="meta">Days</span></div>
    <form method="post" action="<?= e(url('/admin/retention')) ?>" novalidate><?= csrf_field() ?>
      <div class="form-grid">
        <?php foreach ($labels as $k => [$label, , $min]): ?>
          <div class="field"><label for="f-<?= e($k) ?>"><?= e($label) ?> <span class="optional">at least <?= (int) $min ?></span></label>
            <input class="input" id="f-<?= e($k) ?>" name="<?= e($k) ?>" inputmode="numeric" value="<?= (int) $policies[$k] ?>"<?= aria_invalid($errors, $k) ?>><?= field_error($errors, $k) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Save</button></div>
    </form>
    <p class="hint mt-4">Schedule <span class="ref">php bin/retention.php</span> daily to apply these automatically.</p>
  </section>
  <section class="panel span-7" aria-labelledby="req">
    <div class="panel__head"><h2 id="req">Account deletion requests</h2></div>
    <?php if ($requests === []): ?>
      <p class="empty">No requests.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($requests as $r): ?>
          <li><span class="grow"><?= e($r['user_label']) ?><span class="sub">Asked <?= e(fmt_datetime($r['created_at'])) ?><?= $r['reason'] ? ' · ' . e($r['reason']) : '' ?></span>
            <?php if ($r['status'] !== 'requested'): ?><span class="sub"><?= e(ucfirst($r['status'])) ?> by <?= e($r['processed_by_name'] ?? '—') ?> on <?= e(fmt_date($r['processed_at'])) ?><?= $r['decision_note'] ? ': ' . e($r['decision_note']) : '' ?></span><?php endif; ?></span>
            <?php if ($r['status'] === 'requested'): ?>
              <details class="confirm"><summary>Decide</summary><div class="confirm__body">
                <form method="post" action="<?= e(url('/admin/retention/requests/' . (int) $r['id'])) ?>"><?= csrf_field() ?>
                  <div class="field"><label for="n-<?= (int) $r['id'] ?>">Note <span class="optional">required to decline</span></label><input class="input" id="n-<?= (int) $r['id'] ?>" name="note" maxlength="500"></div>
                  <div class="cluster mt-4"><button class="btn btn--danger btn--small" name="decision" value="complete" type="submit">Delete the account</button><button class="btn btn--secondary btn--small" name="decision" value="decline" type="submit">Decline</button></div>
                </form>
              </div></details>
            <?php else: ?><span class="status status--<?= $r['status'] === 'completed' ? 'ok' : 'cancelled' ?>"><?= e(ucfirst($r['status'])) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
