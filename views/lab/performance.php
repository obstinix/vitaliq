<?php
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Support\SvgChart;

/*
 * Question: "Are we reporting on time?"
 * Biggest block: turnaround by test against its target.
 */
$p = $perf;
$bars = array_map(static fn ($d) => ['label' => date('j M', (int) strtotime($d['date'])), 'value' => $d['n']], $p['daily']);
if ($bars !== []) {
    $bars[count($bars) - 1]['highlight'] = true;
}
?>
<div class="app-head">
  <div>
    <h1>Workload and turnaround</h1>
    <p>Last <?= (int) $p['days'] ?> days: <?= fmt_number($p['verified']) ?> results verified, <?= fmt_number($p['rejected']) ?> of <?= fmt_number($p['samples']) ?> samples rejected<?= $p['rejection_rate'] !== null ? ' (' . number_format($p['rejection_rate'], 1) . '%)' : '' ?>.</p>
  </div>
  <nav class="cluster" aria-label="Period">
    <?php foreach ([7, 30, 90] as $d): ?><a class="btn btn--small <?= $p['days'] === $d ? 'btn--primary' : 'btn--secondary' ?>" href="<?= e(url('/lab/performance', ['days' => $d])) ?>"<?= $p['days'] === $d ? ' aria-current="page"' : '' ?>><?= $d ?> days</a><?php endforeach; ?>
  </nav>
</div>
<section class="kpis" aria-label="Period figures">
  <div class="kpi"><span class="kpi__value"><?= fmt_number($p['verified']) ?></span><span class="kpi__label">Results verified</span></div>
  <div class="kpi"><span class="kpi__value"><?= $p['rejection_rate'] === null ? '<span class="faint">—</span>' : e(number_format($p['rejection_rate'], 1)) . '<small>%</small>' ?></span><span class="kpi__label">Sample rejection rate</span></div>
  <div class="kpi"><span class="kpi__value"><?= fmt_number($p['returned']) ?></span><span class="kpi__label">Results sent back</span></div>
  <div class="kpi"><span class="kpi__value"><?= fmt_number($p['critical']) ?></span><span class="kpi__label">Critical results</span></div>
</section>
<div class="vq-grid">
  <section class="panel span-8" aria-labelledby="tat">
    <div class="panel__head"><h2 id="tat">Turnaround by test</h2><span class="meta">Order to verified result, hours</span></div>
    <?php if ($p['tat'] === []): ?>
      <p class="empty">No results verified in this period.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th scope="col">Test</th><th scope="col" class="num">Results</th><th scope="col" class="num">Median</th><th scope="col" class="num">90th centile</th><th scope="col" class="num">Target</th><th scope="col" class="num">Within target</th></tr></thead>
        <tbody>
          <?php foreach ($p['tat'] as $t): ?>
            <tr><td><?= e($t['name']) ?></td><td class="num"><?= (int) $t['n'] ?></td><td class="num"><?= e(number_format($t['median_h'], 1)) ?></td><td class="num"><?= e(number_format($t['p90_h'], 1)) ?></td><td class="num"><?= (int) $t['target_h'] ?></td>
              <td class="num"><span class="<?= $t['within_pct'] < 90 ? 'flag flag--high' : '' ?>"><?= e(number_format($t['within_pct'], 0)) ?>%</span></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
  <section class="panel span-4" aria-labelledby="rej">
    <div class="panel__head"><h2 id="rej">Rejections by reason</h2></div>
    <?php if ($p['rejections'] === []): ?>
      <p class="empty">No samples rejected in this period.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($p['rejections'] as $r): ?>
          <li><span class="grow"><?= e(LaboratoryService::REJECTION_REASONS[$r['reason']] ?? '—') ?></span><span class="end num"><?= (int) $r['n'] ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <section class="panel span-8" aria-labelledby="thr">
    <div class="panel__head"><h2 id="thr">Results verified per day</h2><span class="meta">Today in teal</span></div>
    <?= SvgChart::bars($bars, 'Results verified per day over the last ' . (int) $p['days'] . ' days; ' . (int) $p['verified'] . ' in total.') ?>
  </section>
  <section class="panel span-4" aria-labelledby="stf">
    <div class="panel__head"><h2 id="stf">Today by person</h2></div>
    <?php if ($workload['staff'] === []): ?>
      <p class="empty">No results entered or verified today.</p>
    <?php else: ?>
      <table class="table"><thead><tr><th scope="col">Person</th><th scope="col" class="num">Entered</th><th scope="col" class="num">Verified</th></tr></thead><tbody>
        <?php foreach ($workload['staff'] as $s): ?><tr><td><?= e($s['full_name']) ?></td><td class="num"><?= (int) $s['entered'] ?></td><td class="num"><?= (int) $s['verified'] ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </section>
</div>
