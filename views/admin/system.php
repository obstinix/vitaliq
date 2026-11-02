<?php
$fails = count(array_filter($checks, static fn ($c) => $c['state'] === 'fail'));
$warns = count(array_filter($checks, static fn ($c) => $c['state'] === 'warn'));
?>
<div class="app-head">
  <div>
    <h1>System health</h1>
    <p><?= $fails === 0 && $warns === 0 ? 'Every check passed.' : $fails . ' failing, ' . $warns . ' to watch.' ?> Checked <?= e(fmt_datetime(app()->clock->nowSql())) ?>. Uptime monitors can poll <span class="ref">/api/health</span>.</p>
  </div>
  <a class="btn btn--secondary" href="<?= e(url('/admin/system')) ?>">Check again</a>
</div>
<section class="panel">
  <table class="table">
    <thead><tr><th scope="col">Check</th><th scope="col">State</th><th scope="col">Measured</th><th scope="col">Detail</th></tr></thead>
    <tbody>
      <?php foreach ($checks as $c): ?>
        <tr><td><?= e($c['name']) ?></td>
          <td><span class="status status--<?= e($c['state'] === 'ok' ? 'ok' : ($c['state'] === 'warn' ? 'warning' : 'critical')) ?>"><?= e(['ok' => 'OK', 'warn' => 'Watch', 'fail' => 'Failing'][$c['state']]) ?></span></td>
          <td class="num"><?= e($c['value']) ?></td><td><?= e($c['detail']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
