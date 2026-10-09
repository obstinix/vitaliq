<?php
use Vitaliq\Support\Labels;

$m = $metrics;
$c = $m['counts'];
$pipeline = ['submitted', 'assigned', 'sample_collected', 'in_progress', 'completed', 'result_available', 'cancelled', 'rejected'];
$max = max(1, ...array_values($c));
?>
<div class="app-head">
  <div>
    <p class="eyebrow">Operations</p>
    <h1>Dashboard</h1>
    <p>Every figure is counted from the database when this page loads.</p>
  </div>
  <div class="cluster">
    <a class="btn btn--secondary" href="<?= e(url('/admin/reports')) ?>">Reports</a>
    <a class="btn btn--primary" href="<?= e(url('/admin/requests')) ?>">Open queue<?= $c['submitted'] > 0 ? ' (' . (int) $c['submitted'] . ')' : '' ?></a>
  </div>
</div>

<section class="kpis" aria-label="Key figures">
  <div class="kpi"><span class="kpi__value"><?= fmt_number($m['patients']) ?></span><span class="kpi__label">patients on record (<?= fmt_number($m['accounts']) ?> with accounts)</span></div>
  <div class="kpi"><span class="kpi__value"><?= fmt_number($m['total_requests']) ?></span><span class="kpi__label">testing requests in total</span></div>
  <div class="kpi<?= $m['new_24h'] > 0 ? ' kpi--alert' : '' ?>"><a href="<?= e(url('/admin/requests', ['status' => 'submitted'])) ?>"><span class="kpi__value"><?= fmt_number($m['new_24h']) ?></span><span class="kpi__label">submitted in the last 24 hours</span></a></div>
  <div class="kpi"><span class="kpi__value"><?= $m['avg_turnaround_hours'] === null ? '—' : e(number_format($m['avg_turnaround_hours'], 1)) ?></span><span class="kpi__label">average hours from request to released result (30 days)</span></div>
  <div class="kpi"><span class="kpi__value"><?= e(pct($m['released_positive'], $m['released_total'], 1)) ?></span><span class="kpi__label">positivity among <?= fmt_number($m['released_total']) ?> released results</span></div>
</section>

<h2 class="h3-like">Pipeline</h2>
<nav class="pipeline" aria-label="Requests by status">
  <?php foreach ($pipeline as $status): ?>
    <a href="<?= e(url('/admin/requests', ['status' => $status])) ?>">
      <span class="n"><?= fmt_number($c[$status]) ?></span>
      <span class="l"><?= e(Labels::STATUSES[$status]) ?></span>
      <span class="bar" aria-hidden="true"><i data-pct="<?= e(number_format($c[$status] / $max * 100, 2, '.', '')) ?>"></i></span>
    </a>
  <?php endforeach; ?>
</nav>

<div class="grid-2">
  <div class="stack">
    <section class="panel" aria-labelledby="trend-title">
      <div class="panel__head"><h2 id="trend-title">Requests and released results, last 30 days</h2></div>
      <ul class="chart-legend" aria-hidden="true">
        <li><i class="legend-submitted"></i>Submitted</li>
        <li><i class="legend-released"></i>Released</li>
      </ul>
      <div class="chart" data-chart="daily" data-source="admin-daily" role="img" aria-label="Line chart of daily submitted requests and released results for the last 30 days. Totals: <?= (int) array_sum(array_column($series, 'submitted')) ?> submitted, <?= (int) array_sum(array_column($series, 'released')) ?> released."></div>
      <?= json_script('admin-daily', $series) ?>
      <?php if (array_sum(array_column($series, 'submitted')) === 0): ?>
        <p class="hint mt-4">No requests in the last 30 days, so the chart is flat.</p>
      <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="activity-title">
      <div class="panel__head"><h2 id="activity-title">Recent activity</h2><a href="<?= e(url('/admin/audit')) ?>">Audit log</a></div>
      <?php if ($activity === []): ?>
        <p class="muted mb-0">No workflow events yet.</p>
      <?php else: ?>
        <ul class="feed">
          <?php foreach ($activity as $a): ?>
            <li>
              <time datetime="<?= e(iso_datetime($a['created_at'])) ?>"><?= e(fmt_date($a['created_at'], 'j M H:i')) ?></time>
              <span><a class="ref" href="<?= e(url('/admin/requests/' . (int) $a['request_id'])) ?>"><?= e($a['reference']) ?></a>
                → <?= e(status_label($a['to_status'])) ?>
                <span class="muted">· <?= e($a['actor_name'] ?? Labels::get(Labels::ROLES, $a['actor_role'])) ?></span></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <section class="panel" aria-labelledby="workload-title">
      <div class="panel__head"><h2 id="workload-title">Phlebotomist workload</h2><a href="<?= e(url('/admin/phlebotomists')) ?>">Manage</a></div>
      <?php if ($workload === []): ?>
        <div class="empty"><h3>No active phlebotomists</h3><p>Add phlebotomists before assigning collections.</p><a class="btn btn--secondary" href="<?= e(url('/admin/phlebotomists/new')) ?>">Add phlebotomist</a></div>
      <?php else: ?>
        <ul class="feed">
          <?php foreach ($workload as $w): ?>
            <li><span class="mono small"><?= e($w['employee_code']) ?></span><span><?= e($w['full_name']) ?> <span class="muted">· <?= e($w['base_city']) ?></span> <strong class="nowrap">— <?= (int) $w['open_collections'] ?> open</strong></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="attention-title">
      <div class="panel__head"><h2 id="attention-title">Needs attention</h2></div>
      <ul class="feed">
        <li><span class="mono small"><?= (int) $c['submitted'] ?></span><a href="<?= e(url('/admin/requests', ['status' => 'submitted'])) ?>">New requests waiting for assignment</a></li>
        <li><span class="mono small"><?= (int) $c['sample_collected'] ?></span><a href="<?= e(url('/admin/requests', ['status' => 'sample_collected'])) ?>">Samples to receive at the laboratory</a></li>
        <li><span class="mono small"><?= (int) $c['completed'] ?></span><a href="<?= e(url('/admin/requests', ['status' => 'completed'])) ?>">Results awaiting authorisation</a></li>
        <li><span class="mono small"><?= (int) $m['pending_comments'] ?></span><a href="<?= e(url('/admin/comments')) ?>">Comments awaiting moderation</a></li>
      </ul>
    </section>
  </div>
</div>
