<?php
$pct = static fn (int $n, int $of): string => $of === 0 ? '—' : number_format($n / $of * 100, 1) . '%';
$imp = $q['imports'];
?>
<div class="app-head">
  <div>
    <h1>Data quality</h1>
    <p>Completeness of the fields the analyses depend on, timeliness of results, and the health of imported returns.</p>
  </div>
</div>
<section class="kpis" aria-label="Quality figures">
  <div class="kpi"><span class="kpi__value"><?= $q['timeliness']['median_h'] === null ? '<span class="faint">—</span>' : e(number_format($q['timeliness']['median_h'], 1)) . '<small>h</small>' ?></span><span class="kpi__label">Median, sample to released result</span><span class="kpi__note"><?= $q['timeliness']['p90_h'] === null ? '' : '90th centile ' . e(number_format($q['timeliness']['p90_h'], 1)) . ' h, ' . fmt_number($q['timeliness']['n']) . ' results' ?></span></div>
  <div class="kpi<?= $q['stuck'] > 0 ? ' kpi--alert' : '' ?>"><span class="kpi__value"><?= fmt_number($q['stuck']) ?></span><span class="kpi__label">Requests unchanged for 3+ days in the laboratory stage</span></div>
  <div class="kpi"><span class="kpi__value"><?= e($pct($q['inconclusive']['n'], $q['inconclusive']['of'])) ?></span><span class="kpi__label">Released results inconclusive or invalid</span><span class="kpi__note"><?= fmt_number($q['inconclusive']['n']) ?> of <?= fmt_number($q['inconclusive']['of']) ?></span></div>
  <div class="kpi"><span class="kpi__value"><?= e($pct((int) ($imp['failed'] ?? 0), (int) ($imp['total'] ?? 0))) ?></span><span class="kpi__label">Imported rows rejected</span><span class="kpi__note"><?= fmt_number($imp['failed'] ?? 0) ?> of <?= fmt_number($imp['total'] ?? 0) ?> in <?= fmt_number($imp['files'] ?? 0) ?> files</span></div>
</section>
<div class="vq-grid">
  <section class="panel span-7" aria-labelledby="comp">
    <div class="panel__head"><h2 id="comp">Completeness</h2></div>
    <table class="table">
      <thead><tr><th scope="col">Field</th><th scope="col" class="num">Recorded</th><th scope="col" class="num">Of</th><th scope="col" class="num">Complete</th></tr></thead>
      <tbody>
        <?php foreach ($q['completeness'] as $c): ?>
          <tr><td><?= e($c['label']) ?></td><td class="num"><?= fmt_number($c['n']) ?></td><td class="num"><?= fmt_number($c['of']) ?></td><td class="num"><?= e($pct($c['n'], $c['of'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="hint mt-4">Requests made before facilities were introduced have no facility; that is expected, not an error.</p>
  </section>
  <section class="panel span-5" aria-labelledby="fresh">
    <div class="panel__head"><h2 id="fresh">Latest imported day</h2><a href="<?= e(url('/surveillance/imports')) ?>">Imports</a></div>
    <ul class="rows">
      <?php foreach ($diseases as $k => $l): ?>
        <li><span class="grow"><?= e($l) ?></span><span class="end num"><?= $q['latestImported'][$k] === null ? '<span class="faint">No imports</span>' : e($q['latestImported'][$k]) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
