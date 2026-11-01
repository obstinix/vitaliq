<?php
use Vitaliq\Support\SvgChart;

/*
 * Question: "Is this disease rising, and where?"
 * Biggest block: positives per day in the period, with the states beside it.
 */
$d = $data;
$c = $d['current'];
$p = $d['previous'];
$pct = static fn (?float $v, int $dp = 1): string => $v === null ? '—' : number_format($v * 100, $dp) . '%';
$change = $p['positives'] > 0 ? ($c['positives'] - $p['positives']) / $p['positives'] * 100 : null;
$name = $diseases[$disease] . ($state !== '' ? ' in ' . $states[$state] : '');
$series = array_map(static fn ($r) => ['label' => date('j M', (int) strtotime($r['date'])), 'value' => $r['positives'], 'highlight' => true], $d['daily']);
?>
<div class="app-head">
  <div>
    <h1>Surveillance</h1>
    <p><?= e($name) ?>: <?= fmt_number($c['positives']) ?> positive of <?= fmt_number($c['tests']) ?> tests in the <?= (int) $days ?> days to <?= e(fmt_date($d['window']['to'])) ?>,
      <?php if ($change === null): ?><?= $c['positives'] > 0 ? '+' . (int) $c['positives'] . ' (none in the previous ' . (int) $days . ' days)' : 'none in the previous period either' ?><?php else: ?><?= ($change >= 0 ? '+' : '') . e(number_format($change, 0)) ?>% against the previous <?= (int) $days ?> days<?php endif; ?>.</p>
  </div>
  <a class="btn btn--secondary" href="<?= e(url('/surveillance/reports', ['disease' => $disease, 'days' => $days, 'state' => $state])) ?>">Export</a>
</div>
<?= $view->partial('surveillance-scope', ['base' => '/surveillance', 'disease' => $disease, 'days' => $days, 'state' => $state, 'diseases' => $diseases, 'states' => $states]) ?>

<section class="kpis mt-4" aria-label="Period figures">
  <div class="kpi"><span class="kpi__value"><?= fmt_number($c['positives']) ?></span><span class="kpi__label">Positive results</span><span class="kpi__note"><?= fmt_number($p['positives']) ?> in the previous <?= (int) $days ?> days</span></div>
  <div class="kpi"><span class="kpi__value"><?= fmt_number($c['tests']) ?></span><span class="kpi__label">Tests with a definite result</span><span class="kpi__note"><?= fmt_number($p['tests']) ?> in the previous <?= (int) $days ?> days</span></div>
  <div class="kpi"><span class="kpi__value"><?= e($pct($c['ci']['p'])) ?></span><span class="kpi__label">Positivity</span><span class="kpi__note"><?= $c['ci']['p'] === null ? 'No tests' : '95% CI ' . e($pct($c['ci']['low'])) . '–' . e($pct($c['ci']['high'])) ?></span></div>
  <div class="kpi<?= $openAlerts > 0 ? ' kpi--alert' : '' ?>"><a href="<?= e(url('/surveillance/alerts')) ?>"><span class="kpi__value"><?= (int) $openAlerts ?></span><span class="kpi__label">Open outbreak alerts</span></a></div>
</section>

<div class="vq-grid">
  <section class="panel span-8" aria-labelledby="daily">
    <div class="panel__head"><h2 id="daily">Positive results per day</h2><span class="meta">Today is still filling</span></div>
    <?= SvgChart::bars($series, 'Positive results per day for ' . $name . ', ' . $c['positives'] . ' in total.') ?>
    <?php if ($c['tests'] === 0): ?><p class="hint mt-4">No definite results in this period, so every bar is zero.</p><?php endif; ?>
  </section>
  <section class="panel span-4" aria-labelledby="by-state">
    <div class="panel__head"><h2 id="by-state">By state</h2><a href="<?= e(url('/surveillance/map', ['disease' => $disease, 'days' => $days])) ?>">Map</a></div>
    <?php if ($d['byState'] === []): ?>
      <p class="empty">No results in this period.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th scope="col">State</th><th scope="col" class="num">Positive</th><th scope="col" class="num">Positivity</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($d['byState'], 0, 6) as $r): ?>
            <tr><td><a href="<?= e(url('/surveillance', ['disease' => $disease, 'days' => $days, 'state' => $r['state_code']])) ?>"><?= e($r['name']) ?></a></td>
              <td class="num"><?= fmt_number($r['positives']) ?><span class="sub">of <?= fmt_number($r['tests']) ?></span></td>
              <td class="num"><?= $r['tests'] >= 10 ? e($pct($r['rate'])) : '<span class="faint" title="Fewer than 10 tests">—</span>' ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
  <section class="panel span-8" aria-labelledby="sources">
    <div class="panel__head"><h2 id="sources">Where the figures come from</h2></div>
    <table class="table">
      <thead><tr><th scope="col">Source</th><th scope="col" class="num">Tests</th><th scope="col" class="num">Positive</th></tr></thead>
      <tbody>
        <?php foreach (['workflow' => 'Testing requests (released results)', 'laboratory' => 'Laboratory orders (verified results)', 'imported' => 'Imported returns'] as $k => $l): ?>
          <tr><td><?= e($l) ?></td><td class="num"><?= fmt_number($d['sources'][$k]['tests'] ?? 0) ?></td><td class="num"><?= fmt_number($d['sources'][$k]['positives'] ?? 0) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <section class="panel span-4" aria-labelledby="open-alerts">
    <div class="panel__head"><h2 id="open-alerts">Open alerts</h2><a href="<?= e(url('/surveillance/alerts')) ?>">All</a></div>
    <?php if ($alerts === []): ?>
      <p class="empty">No open alerts.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($alerts as $a): ?>
          <li><span class="grow"><?= e($diseases[$a['disease']] ?? $a['disease']) ?> · <?= e($a['state_name']) ?><span class="sub"><?= (int) $a['positives'] ?> of <?= (int) $a['tests'] ?> · to <?= e($a['window_end']) ?></span></span><span class="status status--open">Open</span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
<p class="disclaimer">These figures count tests and positive results. They depend on who is tested and when, and are not an estimate of the true number of infections.</p>
