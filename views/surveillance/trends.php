<?php
use Vitaliq\Support\SvgChart;

/*
 * Question: "Is it growing, and how sure are we?"
 * The trend line carries the hue; the projection is dashed with its 95% band.
 */
$daily = $data['daily'];
$t = $data['trend'];
$name = $diseases[$disease] . ($state !== '' ? ' in ' . $states[$state] : '');
$points = [];
foreach ($daily as $i => $d) {
    $points[] = ['label' => date('j M', (int) strtotime($d['date'])), 'value' => $data['ma'][$i] !== null ? round($data['ma'][$i], 2) : null];
}
$forecastFrom = null;
if ($t !== null) {
    $forecastFrom = count($points) - 1;
    $points[$forecastFrom]['low'] = $points[$forecastFrom]['value'];
    $points[$forecastFrom]['high'] = $points[$forecastFrom]['value'];
    $last = end($daily);
    $base = strtotime((string) $last['date']);
    foreach ($t['projection'] as $pr) {
        $points[] = ['label' => date('j M', $base + $pr['t'] * 86400), 'value' => round($pr['value'], 2), 'low' => round($pr['low'], 2), 'high' => round($pr['high'], 2)];
    }
}
$pct = static fn (float $v): string => ($v >= 0 ? '+' : '') . number_format($v * 100, 1) . '%';
$months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$years = [];
foreach ($data['byMonthOfYear'] as $m) {
    $years += array_fill_keys(array_keys($m['years']), true);
}
ksort($years);
$peak = max(1, ...array_map(static fn ($m) => $m['positives'], $data['byMonthOfYear']));
?>
<div class="app-head">
  <div>
    <h1>Trends and seasons</h1>
    <p><?= e($name) ?>.
      <?php if ($t === null): ?>Too few positive results in the last 21 days to estimate a trend.<?php else: ?>
        Positive results changed by <?= e($pct($t['growth'])) ?> a day over the last 21 days (95% CI <?= e($pct($t['growth_low'])) ?> to <?= e($pct($t['growth_high'])) ?>)<?php if ($t['growth_low'] > 0): ?>: rising.<?php elseif ($t['growth_high'] < 0): ?>: falling.<?php else: ?>: no clear direction.<?php endif; ?>
      <?php endif; ?></p>
  </div>
</div>
<?= $view->partial('surveillance-scope', ['base' => '/surveillance/trends', 'disease' => $disease, 'days' => $days, 'state' => $state, 'diseases' => $diseases, 'states' => $states]) ?>
<div class="vq-grid mt-4">
  <section class="panel span-8" aria-labelledby="trend">
    <div class="panel__head"><h2 id="trend">Positive results, 7-day average</h2><span class="meta">Last 56 days, then 7 days projected</span></div>
    <ul class="legend"><li><i></i>7-day average</li><?php if ($t !== null): ?><li><i class="dash"></i>Projection</li><li><i class="band"></i>95% prediction interval</li><?php endif; ?></ul>
    <?= SvgChart::line($points, 'Seven-day average of positive results for ' . $name . ($t !== null ? ', with a seven-day projection and its 95% prediction interval' : ''), $forecastFrom) ?>
  </section>
  <section class="panel span-4" aria-labelledby="est">
    <div class="panel__head"><h2 id="est">Estimate</h2></div>
    <?php if ($t === null): ?>
      <p class="empty">A trend needs at least 10 positive results in the last 21 days.</p>
    <?php else: ?>
      <dl class="details">
        <dt>Daily change</dt><dd class="num"><?= e($pct($t['growth'])) ?></dd>
        <dt>95% interval</dt><dd class="num"><?= e($pct($t['growth_low'])) ?> to <?= e($pct($t['growth_high'])) ?></dd>
        <dt><?= $t['growth'] >= 0 ? 'Doubling time' : 'Halving time' ?></dt><dd class="num"><?= $t['doubling_days'] === null ? '—' : e(number_format(abs($t['doubling_days']), 1)) . ' days' ?></dd>
        <dt>In 7 days</dt><dd class="num"><?= e(number_format(end($t['projection'])['value'], 1)) ?> a day <span class="sub">95% PI <?= e(number_format(end($t['projection'])['low'], 1)) ?>–<?= e(number_format(end($t['projection'])['high'], 1)) ?></span></dd>
      </dl>
      <p class="disclaimer">A statistical projection of the recent trend in positive results, not a forecast of infections. Changes in testing change the figures.</p>
    <?php endif; ?>
  </section>
  <section class="panel span-12" aria-labelledby="season">
    <div class="panel__head"><h2 id="season">Positive results by month</h2><span class="meta">Up to 3 years; the busiest month in teal</span></div>
    <?php if ($years === []): ?>
      <p class="empty">No monthly history yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th scope="col">Month</th><?php foreach (array_keys($years) as $y): ?><th scope="col" class="num"><?= e((string) $y) ?></th><?php endforeach; ?><th scope="col" class="num">All years</th><th scope="col" class="num">Positivity</th></tr></thead>
          <tbody>
            <?php foreach ($data['byMonthOfYear'] as $mo => $m): ?>
              <tr><th scope="row"><?= e($months[$mo]) ?></th>
                <?php foreach (array_keys($years) as $y): ?><td class="num"><?= isset($m['years'][$y]) ? fmt_number($m['years'][$y]) : '<span class="faint">—</span>' ?></td><?php endforeach; ?>
                <td class="num"><strong class="<?= $m['positives'] === $peak && $peak > 1 ? 'peak' : '' ?>"><?= fmt_number($m['positives']) ?></strong></td>
                <td class="num"><?= $m['tests'] > 0 ? e(number_format($m['positives'] / $m['tests'] * 100, 1)) . '%' : '<span class="faint">—</span>' ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
