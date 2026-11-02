<div class="app-head">
  <div>
    <h1>Audit analytics</h1>
    <p>Last <?= (int) $a['days'] ?> days: <?= fmt_number($a['total']) ?> recorded events, <?= fmt_number($a['failed']) ?> failed sign-ins<?= $a['noisyConnections'] > 0 ? ', ' . (int) $a['noisyConnections'] . ' connections with 10 or more failures' : '' ?>.</p>
  </div>
  <nav class="cluster" aria-label="Period"><?php foreach ([7, 30, 90] as $d): ?><a class="btn btn--small <?= $a['days'] === $d ? 'btn--primary' : 'btn--secondary' ?>" href="<?= e(url('/admin/audit/analytics', ['days' => $d])) ?>"><?= $d ?> days</a><?php endforeach; ?></nav>
</div>
<div class="vq-grid">
  <section class="panel span-8" aria-labelledby="per-day">
    <div class="panel__head"><h2 id="per-day">Events per day</h2><a href="<?= e(url('/admin/audit')) ?>">Audit log</a></div>
    <?= $chart ?>
  </section>
  <section class="panel span-4" aria-labelledby="unusual">
    <div class="panel__head"><h2 id="unusual">Unusual activity</h2><span class="meta">20+ downloads or exports in a day</span></div>
    <?php if ($a['unusual'] === []): ?>
      <p class="empty">Nothing unusual in this period.</p>
    <?php else: ?>
      <ul class="rows"><?php foreach ($a['unusual'] as $u): ?><li><span class="grow"><?= e($u['full_name']) ?><span class="sub"><?= e($u['role']) ?> · <?= e($u['d']) ?></span></span><span class="end num flag flag--high"><?= (int) $u['n'] ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
  <section class="panel span-6" aria-labelledby="actions">
    <div class="panel__head"><h2 id="actions">Most frequent actions</h2></div>
    <table class="table"><thead><tr><th scope="col">Action</th><th scope="col" class="num">Events</th></tr></thead><tbody>
      <?php foreach ($a['topActions'] as $r): ?><tr><td class="ref"><?= e($r['action']) ?></td><td class="num"><?= fmt_number($r['n']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
  <section class="panel span-6" aria-labelledby="actors">
    <div class="panel__head"><h2 id="actors">Most active people</h2></div>
    <table class="table"><thead><tr><th scope="col">Person</th><th scope="col">Role</th><th scope="col" class="num">Events</th></tr></thead><tbody>
      <?php foreach ($a['topActors'] as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= e($r['role']) ?></td><td class="num"><?= fmt_number($r['n']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
</div>
