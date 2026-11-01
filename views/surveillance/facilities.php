<?php
use Vitaliq\Support\SvgChart;

$o = $data['overall'];
$max = max(0.05, ...array_map(static fn ($r) => (float) ($r['high'] ?? 0), $data['rows'] ?: [['high' => 0.05]]));
$pct = static fn (?float $v): string => $v === null ? '—' : number_format($v * 100, 1) . '%';
$flags = ['above' => ['Above overall', 'critical'], 'below' => ['Below overall', 'active'], 'within' => ['Within range', 'ok'], 'none' => ['Too few tests', 'cancelled']];
$above = count(array_filter($data['rows'], static fn ($r) => $r['flag'] === 'above'));
?>
<div class="app-head">
  <div>
    <h1>Facility comparison</h1>
    <p><?= e($diseases[$disease]) ?>, last <?= (int) $days ?> days. Overall positivity <?= e($pct($o['p'])) ?> across <?= fmt_number($o['tests']) ?> tests; <?= $above ?> <?= $above === 1 ? 'facility is' : 'facilities are' ?> clearly above it.</p>
  </div>
</div>
<?= $view->partial('surveillance-scope', ['base' => '/surveillance/facilities', 'disease' => $disease, 'days' => $days, 'state' => '', 'diseases' => $diseases, 'states' => $states, 'noState' => true]) ?>
<section class="panel mt-4">
  <?php if ($data['rows'] === []): ?>
    <p class="empty">No results in this period.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead><tr><th scope="col">Facility</th><th scope="col" class="num">Tests</th><th scope="col" class="num">Positive</th><th scope="col" class="num">Positivity</th><th scope="col">95% interval against overall (red line)</th><th scope="col" class="num">Median turnaround</th><th scope="col">Compared with overall</th></tr></thead>
        <tbody>
          <?php foreach ($data['rows'] as $r): ?>
            <tr>
              <td data-label="Facility"><?= e($r['name']) ?><?= $r['city'] ? '<span class="sub">' . e($r['city']) . '</span>' : '' ?></td>
              <td data-label="Tests" class="num"><?= fmt_number($r['tests']) ?></td>
              <td data-label="Positive" class="num"><?= fmt_number($r['positives']) ?></td>
              <td data-label="Positivity" class="num"><?= e($pct($r['p'])) ?></td>
              <td data-label="Interval"><?= SvgChart::ciBar($r['p'], $r['low'], $r['high'], $max, $o['p']) ?></td>
              <td data-label="Median turnaround" class="num"><?= $r['tat_h'] === null ? '<span class="faint">—</span>' : e(number_format($r['tat_h'], 1)) . ' h' ?></td>
              <td data-label="Compared"><span class="status status--<?= e($flags[$r['flag']][1]) ?>"><?= e($flags[$r['flag']][0]) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <p class="hint mt-4">A facility is marked above or below only when its whole 95% interval lies on one side of the overall rate. Differences can reflect who each facility tests, not quality of care. Turnaround is request to released result for testing requests.</p>
</section>
