<?php
use Vitaliq\Services\SurveillanceService;
use Vitaliq\Support\SvgChart;

$d = $data;
$all = array_merge($d['sex'], $d['age']);
$max = max(0.05, ...array_map(static fn ($r) => (float) ($r['high'] ?? 0), $all ?: [['high' => 0.05]]));
$pct = static fn (?float $v): string => $v === null ? '—' : number_format($v * 100, 1) . '%';
$rows = static function (array $list, string $key, callable $label) use ($view, $max, $pct): string {
    $h = '';
    foreach ($list as $r) {
        $h .= '<tr><td>' . e($label($r[$key])) . '</td><td class="num">' . fmt_number($r['tests']) . '</td><td class="num">' . fmt_number($r['positives']) . '</td>'
            . '<td class="num">' . e($pct($r['p'])) . '<span class="sub">' . ($r['p'] === null ? '' : e($pct($r['low']) . '–' . $pct($r['high']))) . '</span></td>'
            . '<td>' . SvgChart::ciBar($r['p'], $r['low'], $r['high'], $max) . '</td></tr>';
    }
    return $h;
};
?>
<div class="app-head">
  <div>
    <h1>Demographics</h1>
    <p><?= e($diseases[$disease]) ?><?= $state !== '' ? ' in ' . e($states[$state]) : '' ?>, last <?= (int) $days ?> days. Positivity with 95% intervals; wide bars mean few tests.</p>
  </div>
</div>
<?= $view->partial('surveillance-scope', ['base' => '/surveillance/demographics', 'disease' => $disease, 'days' => $days, 'state' => $state, 'diseases' => $diseases, 'states' => $states]) ?>
<div class="vq-grid mt-4">
  <section class="panel span-6" aria-labelledby="by-age">
    <div class="panel__head"><h2 id="by-age">By age</h2></div>
    <?php if ($d['age'] === []): ?><p class="empty">No results with an age in this period.</p><?php else: ?>
      <table class="table"><thead><tr><th scope="col">Age</th><th scope="col" class="num">Tests</th><th scope="col" class="num">Positive</th><th scope="col" class="num">Positivity</th><th scope="col"><span class="visually-hidden">Interval</span></th></tr></thead>
        <tbody><?= $rows($d['age'], 'age_band', static fn ($k) => SurveillanceService::AGE_BANDS[$k] ?? $k) ?></tbody></table>
    <?php endif; ?>
  </section>
  <section class="panel span-6" aria-labelledby="by-sex">
    <div class="panel__head"><h2 id="by-sex">By sex</h2></div>
    <?php if ($d['sex'] === []): ?><p class="empty">No results with sex recorded in this period.</p><?php else: ?>
      <table class="table"><thead><tr><th scope="col">Sex</th><th scope="col" class="num">Tests</th><th scope="col" class="num">Positive</th><th scope="col" class="num">Positivity</th><th scope="col"><span class="visually-hidden">Interval</span></th></tr></thead>
        <tbody><?= $rows($d['sex'], 'sex', static fn ($k) => ucfirst((string) $k)) ?></tbody></table>
    <?php endif; ?>
    <p class="hint mt-4"><?= fmt_number($d['unknown']['sex']) ?> tests without sex and <?= fmt_number($d['unknown']['age']) ?> without an age band (mostly imported returns) are left out of these tables.</p>
  </section>
</div>
