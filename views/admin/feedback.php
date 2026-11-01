<?php
$s = $data['summary'];
$total = (int) ($s['total'] ?? 0);
?>
<div class="app-head">
  <div>
    <h1>Patient feedback</h1>
    <p><?= $total === 0 ? 'No feedback received yet.' : (int) $s['unread'] . ' new of ' . $total . '; ' . (int) $s['low'] . ' rated 1 or 2.' ?></p>
  </div>
</div>
<section class="kpis" aria-label="Feedback summary">
  <div class="kpi"><span class="kpi__value"><?= $total ?></span><span class="kpi__label">Received</span></div>
  <div class="kpi"><span class="kpi__value"><?= $total > 0 ? e(number_format((float) $s['avg_rating'], 1)) . '<small>/5</small>' : '<span class="faint">—</span>' ?></span><span class="kpi__label">Average rating</span></div>
  <div class="kpi<?= (int) ($s['unread'] ?? 0) > 0 ? ' kpi--alert' : '' ?>"><span class="kpi__value"><?= (int) ($s['unread'] ?? 0) ?></span><span class="kpi__label">Waiting for review</span></div>
  <div class="kpi"><span class="kpi__value"><?= (int) ($s['low'] ?? 0) ?></span><span class="kpi__label">Rated 1 or 2</span></div>
</section>
<div class="vq-grid">
  <section class="panel span-8" aria-labelledby="fb-rows">
    <div class="panel__head"><h2 id="fb-rows">Messages</h2>
      <nav class="cluster" aria-label="Filter">
        <?php foreach (['' => 'All', 'new' => 'New', 'reviewed' => 'Reviewed', 'actioned' => 'Actioned'] as $k => $l): ?>
          <a href="<?= e(url('/admin/feedback', ['status' => $k])) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <?php if ($data['rows'] === []): ?>
      <p class="empty">No feedback in this view.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($data['rows'] as $f): ?>
          <li>
            <span class="grow">
              <strong class="num"><?= (int) $f['rating'] ?>/5</strong> · <?= e($categories[$f['category']] ?? $f['category']) ?><?= $f['reference'] ? ' · <span class="ref">' . e($f['reference']) . '</span>' : '' ?>
              <span class="sub"><?= e($f['full_name'] ?? 'Deleted account') ?> · <?= e(fmt_datetime($f['created_at'])) ?></span>
              <?php if ($f['comments']): ?><span class="sub"><?= e($f['comments']) ?></span><?php endif; ?>
              <?php if ($f['admin_note']): ?><span class="sub">Reply: <?= e($f['admin_note']) ?></span><?php endif; ?>
            </span>
            <span class="status status--<?= e($f['status'] === 'new' ? 'pending' : 'resolved') ?>"><?= e(ucfirst((string) $f['status'])) ?></span>
            <?php if ($f['status'] === 'new'): ?>
              <details class="confirm"><summary>Review</summary><div class="confirm__body">
                <form method="post" action="<?= e(url('/admin/feedback/' . (int) $f['id'])) ?>">
                  <?= csrf_field() ?>
                  <div class="field"><label for="n-<?= (int) $f['id'] ?>">Reply shown to the patient <span class="optional">optional</span></label><input class="input" id="n-<?= (int) $f['id'] ?>" name="note" maxlength="500"></div>
                  <div class="cluster mt-4"><button class="btn btn--secondary btn--small" name="status" value="reviewed" type="submit">Mark reviewed</button><button class="btn btn--primary btn--small" name="status" value="actioned" type="submit">Mark actioned</button></div>
                </form>
              </div></details>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <section class="panel span-4" aria-labelledby="fb-cat">
    <div class="panel__head"><h2 id="fb-cat">By topic</h2></div>
    <?php if ($s['by_category'] === []): ?>
      <p class="empty">No feedback yet.</p>
    <?php else: ?>
      <table class="table"><thead><tr><th scope="col">Topic</th><th scope="col" class="num">Count</th><th scope="col" class="num">Average</th></tr></thead><tbody>
        <?php foreach ($s['by_category'] as $c): ?>
          <tr><td><?= e($categories[$c['category']] ?? $c['category']) ?></td><td class="num"><?= (int) $c['n'] ?></td><td class="num"><?= e(number_format((float) $c['avg_rating'], 1)) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </section>
</div>
