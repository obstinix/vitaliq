<?php
$r = $run;
$runTypes = ['regex_suite' => 'Regex suite', 'manual' => 'Manual execution', 'phpunit' => 'PHPUnit import', 'coverage' => 'Catalogue coverage from PHPUnit'];
?>
<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/qa/runs')) ?>">Execution history</a></p>
    <h1>Run #<?= (int) $r['id'] ?></h1>
    <p><?= e($runTypes[$r['run_type']] ?? $r['run_type']) ?> · started <?= e(fmt_datetime($r['started_at'])) ?> · <?= e($r['executed_by'] ?? ($r['triggered_from'] === 'cli' ? 'command line' : '—')) ?></p>
  </div>
</div>

<section class="kpis" aria-label="Run totals">
  <div class="kpi"><span class="kpi__value"><?= (int) $r['total'] ?></span><span class="kpi__label">executed</span></div>
  <div class="kpi"><span class="kpi__value"><?= (int) $r['passed'] ?></span><span class="kpi__label">passed</span></div>
  <div class="kpi<?= (int) $r['failed'] > 0 ? ' kpi--alert' : '' ?>"><span class="kpi__value"><?= (int) $r['failed'] ?></span><span class="kpi__label">failed</span></div>
  <div class="kpi"><span class="kpi__value"><?= (int) $r['blocked'] ?></span><span class="kpi__label">blocked or skipped</span></div>
  <div class="kpi"><span class="kpi__value"><?= e(pct((int) $r['passed'], (int) $r['total'], 1)) ?></span><span class="kpi__label">pass rate</span></div>
</section>
<p class="hint">Environment: <?= e($r['environment']) ?><?= $r['notes'] ? ' · ' . e($r['notes']) : '' ?></p>

<?php if ($r['modules'] !== []): ?>
  <h2 class="h3-like mt-5">By module</h2>
  <div class="table-wrap mb-5">
    <table class="table table--stack">
      <thead><tr><th scope="col">Module</th><th scope="col" class="num">Executed</th><th scope="col" class="num">Passed</th><th scope="col" class="num">Failed</th></tr></thead>
      <tbody>
        <?php foreach ($r['modules'] as $m): ?>
          <tr><td data-label="Module"><?= e($m['module']) ?></td><td data-label="Executed" class="num"><?= (int) $m['total'] ?></td><td data-label="Passed" class="num"><?= (int) $m['passed'] ?></td><td data-label="Failed" class="num"><?= (int) $m['failed'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<nav class="tabs" aria-label="Filter results">
  <?php foreach (['' => 'All', 'fail' => 'Failed', 'pass' => 'Passed', 'blocked' => 'Blocked', 'skipped' => 'Skipped'] as $k => $l): ?>
    <a href="<?= e(url('/qa/runs/' . (int) $r['id'], ['status' => $k])) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a>
  <?php endforeach; ?>
</nav>
<?php if ($r['results'] === []): ?>
  <div class="empty"><h2>No results with this status</h2></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">Test</th><th scope="col">Input</th><th scope="col">Expected</th><th scope="col">Actual</th><th scope="col">Status</th><th scope="col" class="num">ms</th></tr></thead>
      <tbody>
        <?php foreach ($r['results'] as $x): ?>
          <tr>
            <td data-label="Test"><?php if ($x['test_case_id']): ?><a class="mono small" href="<?= e(url('/qa/cases/' . (int) $x['test_case_id'])) ?>"><?= e($x['case_code']) ?></a><br><?php endif; ?><span class="small"><?= e($x['test_case_id'] ? preg_replace('/^\S+\s/', '', $x['test_name']) : $x['test_name']) ?></span></td>
            <td data-label="Input" class="mono small"><?= $x['rule_key'] ? e(show_input($x['input_value'])) : '—' ?></td>
            <td data-label="Expected" class="small"><?= e($x['expected_result'] ?? '—') ?></td>
            <td data-label="Actual" class="small"><?= e($x['actual_result']) ?></td>
            <td data-label="Status"><span class="status status--<?= e($x['status']) ?>"><?= e(ucfirst($x['status'])) ?></span><?= $x['defect_code'] ? '<br><a class="mono small" href="' . e(url('/qa/defects/' . (int) $x['defect_id'])) . '">' . e($x['defect_code']) . '</a>' : '' ?></td>
            <td data-label="ms" class="num small"><?= $x['duration_ms'] !== null ? e($x['duration_ms']) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
