<?php
use Vitaliq\Support\Labels;

$counts = $summary['counts'];
$total = max(1, $summary['total']);
$suppress = static fn (int $n): string => $n > 0 && $n < $smallCell ? '<' . $smallCell : fmt_number($n);
$whoTotal = array_sum(array_column($who['series'], 'new_cases'));
$peak = array_reduce($who['series'], static fn ($c, $r) => $c === null || $r['new_cases'] > $c['new_cases'] ? $r : $c);
?>
<header class="page-head">
  <div class="wrap">
    <p class="eyebrow"><span class="num">Data</span> Testing statistics</p>
    <h1>Numbers with their provenance.</h1>
    <p class="lede">Two different sources, kept clearly apart: activity on this Vitaliq platform, and historical national data that India reported to WHO.</p>
  </div>
</header>

<section class="section" aria-labelledby="platform-title">
  <div class="wrap">
    <p class="eyebrow"><span class="num">01</span> Vitaliq platform</p>
    <h2 id="platform-title">Requests in this database</h2>
    <div class="callout callout--note mb-5">
      <p><strong>Not official statistics.</strong> These figures are counted live from requests stored in this Vitaliq installation<?= $summary['demo_seeded_at'] ? ', which contains synthetic demonstration records seeded on ' . e(fmt_date($summary['demo_seeded_at'])) : '' ?>. Last change: <?= e(fmt_datetime($summary['updated_at'])) ?>.</p>
    </div>

    <div class="figures">
      <div class="figure"><span class="figure__value"><?= fmt_number($summary['total']) ?></span><span class="figure__label">requests recorded</span></div>
      <div class="figure"><span class="figure__value"><?= fmt_number($summary['in_pipeline']) ?></span><span class="figure__label">currently in progress</span></div>
      <div class="figure"><span class="figure__value"><?= fmt_number($summary['released']) ?></span><span class="figure__label">results released</span></div>
      <div class="figure"><span class="figure__value"><?= fmt_number(($summary['by_type']['rt_pcr'] ?? 0)) ?></span><span class="figure__label">RT-PCR requests (<?= fmt_number($summary['by_type']['rapid_antigen'] ?? 0) ?> antigen)</span></div>
    </div>

    <div class="split split--even mt-6">
      <div>
        <h3>Last 30 days</h3>
        <ul class="chart-legend" aria-hidden="true">
          <li><i class="legend-submitted"></i>Requests submitted</li>
          <li><i class="legend-released"></i>Results released</li>
        </ul>
        <div class="chart" data-chart="daily" data-source="platform-daily" role="img" aria-label="Line chart of requests submitted and results released per day over the last 30 days. The same data is in the table below."></div>
        <?= json_script('platform-daily', $series) ?>
        <details class="chart-fallback">
          <summary>Show the daily figures as a table</summary>
          <div class="table-wrap mt-4">
            <table class="table">
              <thead><tr><th scope="col">Date</th><th scope="col" class="num">Submitted</th><th scope="col" class="num">Released</th></tr></thead>
              <tbody>
                <?php foreach (array_reverse($series) as $d): ?>
                  <tr><td><?= e(fmt_date($d['date'], 'D j M')) ?></td><td class="num"><?= (int) $d['submitted'] ?></td><td class="num"><?= (int) $d['released'] ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </details>
      </div>
      <div>
        <h3>Where requests are now</h3>
        <div role="list">
          <?php foreach ($counts as $status => $n): ?>
            <div class="hbar" role="listitem">
              <span><?= e(Labels::STATUSES[$status]) ?></span>
              <span class="hbar__track" aria-hidden="true"><span class="hbar__fill" data-pct="<?= e(number_format($n / $total * 100, 2, '.', '')) ?>"></span></span>
              <span class="hbar__value"><?= fmt_number($n) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <h3 class="mt-6" id="states-title">Requests by state or union territory</h3>
    <p class="muted">Grouped by the state of the collection address recorded on each request. States with no requests are omitted. Positive counts between 1 and <?= (int) $smallCell - 1 ?> are shown as “&lt;<?= (int) $smallCell ?>” to protect privacy.</p>
    <?php if ($states === []): ?>
      <div class="empty"><h3>No requests yet</h3><p>A state summary appears once requests have been submitted.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack" aria-labelledby="states-title">
          <thead><tr><th scope="col">State / UT</th><th scope="col" class="num">Requests</th><th scope="col" class="num">Results released</th><th scope="col" class="num">Positive (released)</th></tr></thead>
          <tbody>
            <?php foreach ($states as $s): ?>
              <tr>
                <td data-label="State / UT"><?= e($s['name']) ?></td>
                <td data-label="Requests" class="num"><?= fmt_number($s['requests']) ?></td>
                <td data-label="Released" class="num"><?= fmt_number($s['released']) ?></td>
                <td data-label="Positive" class="num"><?= e($suppress($s['positive'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section--tint" aria-labelledby="who-title">
  <div class="wrap">
    <p class="eyebrow"><span class="num">02</span> Historical context</p>
    <h2 id="who-title">India: cases reported to WHO, by month</h2>
    <p class="lede">From the first reports in 2020 to the latest month with reported cases (<?= e(fmt_date($peak ? end($who['series'])['month'] . '-01' : null, 'F Y')) ?>). The largest monthly total was <?= fmt_number($peak['new_cases']) ?> in <?= e(fmt_date($peak['month'] . '-01', 'F Y')) ?>.</p>

    <div class="chart" data-chart="monthly" data-source="who-monthly" role="img" aria-label="Bar chart of COVID-19 cases reported by India to WHO each month. The same data is in the table below."></div>
    <?= json_script('who-monthly', $who['series']) ?>

    <dl class="details mt-5">
      <dt>Source</dt><dd><a href="<?= e($who['source_url']) ?>" rel="noopener" target="_blank"><?= e($who['source']) ?></a></dd>
      <dt>Licence</dt><dd><?= e($who['licence']) ?></dd>
      <dt>Downloaded</dt><dd><?= e(fmt_date($who['retrieved_on'])) ?> (latest report date in file: <?= e(fmt_date($who['latest_report_date'])) ?>)</dd>
      <dt>Method</dt><dd><?= e($who['method']) ?></dd>
      <dt>Check</dt><dd>The monthly totals sum to <?= fmt_number($whoTotal) ?>, matching WHO’s cumulative figure of <?= fmt_number($who['latest_cumulative_cases']) ?> cases.</dd>
    </dl>
    <p class="hint mt-4">Reported cases undercount true infections, and reporting practices changed over time, especially after widespread home testing. WHO does not endorse Vitaliq or this presentation.</p>

    <details class="chart-fallback">
      <summary>Show the monthly figures as a table</summary>
      <div class="table-wrap mt-4">
        <table class="table">
          <thead><tr><th scope="col">Month</th><th scope="col" class="num">New cases</th><th scope="col" class="num">New deaths</th></tr></thead>
          <tbody>
            <?php foreach ($who['series'] as $m): ?>
              <tr><td><?= e(fmt_date($m['month'] . '-01', 'M Y')) ?></td><td class="num"><?= fmt_number($m['new_cases']) ?></td><td class="num"><?= fmt_number($m['new_deaths']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  </div>
</section>
