<?php
$t = $summary['totals'];
$runLabels = ['regex_suite' => 'Regex suite', 'phpunit' => 'PHPUnit import', 'coverage' => 'Catalogue coverage', 'manual' => 'Manual execution'];
?>
<div class="app-head">
  <div>
    <p class="eyebrow">STQA laboratory</p>
    <h1>Quality overview</h1>
    <p>Pass and fail figures come only from recorded executions. Cases that have never run are counted as “not run”, not as passes.</p>
  </div>
  <form method="post" action="<?= e(url('/qa/runs')) ?>" data-once>
    <?= csrf_field() ?>
    <button class="btn btn--primary" type="submit" data-busy-label="Running…">Run regex suite now</button>
  </form>
</div>

<section class="kpis" aria-label="Catalogue status">
  <div class="kpi"><span class="kpi__value"><?= fmt_number($t['cases']) ?></span><span class="kpi__label">test cases in the catalogue</span></div>
  <div class="kpi"><span class="kpi__value"><?= fmt_number($t['executed']) ?></span><span class="kpi__label">executed at least once</span></div>
  <div class="kpi"><span class="kpi__value"><?= e(pct($t['pass'], $t['executed'], 1)) ?></span><span class="kpi__label">pass rate of latest results (<?= (int) $t['pass'] ?> pass / <?= (int) $t['fail'] ?> fail / <?= (int) $t['blocked'] ?> blocked)</span></div>
  <div class="kpi<?= $t['not_run'] > 0 ? ' kpi--alert' : '' ?>"><a href="<?= e(url('/qa/cases', ['status' => 'not_run'])) ?>"><span class="kpi__value"><?= fmt_number($t['not_run']) ?></span><span class="kpi__label">never executed</span></a></div>
  <div class="kpi"><a href="<?= e(url('/qa/defects', ['status' => 'open'])) ?>"><span class="kpi__value"><?= fmt_number($summary['open_defects']) ?></span><span class="kpi__label">open or in-progress defects</span></a></div>
</section>

<div class="grid-2">
  <section class="panel" aria-labelledby="modules-title">
    <div class="panel__head"><h2 id="modules-title">Latest result by module</h2><a href="<?= e(url('/qa/cases')) ?>">Catalogue</a></div>
    <?php if ($summary['modules'] === []): ?>
      <div class="empty"><h3>No test cases</h3><p>Load the catalogue with <code>php bin/qa.php sync</code>.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Module</th><th scope="col" class="num">Cases</th><th scope="col" class="num">Pass</th><th scope="col" class="num">Fail</th><th scope="col" class="num">Not run</th><th scope="col">Share</th></tr></thead>
          <tbody>
            <?php foreach ($summary['modules'] as $m): ?>
              <tr>
                <td data-label="Module"><a href="<?= e(url('/qa/cases', ['module' => $m['module']])) ?>"><?= e($m['module']) ?></a></td>
                <td data-label="Cases" class="num"><?= (int) $m['cases'] ?></td>
                <td data-label="Pass" class="num"><?= (int) $m['pass'] ?></td>
                <td data-label="Fail" class="num"><?= (int) $m['fail'] ?></td>
                <td data-label="Not run" class="num"><?= (int) $m['not_run'] + (int) $m['blocked'] ?></td>
                <td data-label="Share">
                  <span class="passbar" role="img" aria-label="<?= (int) $m['pass'] ?> pass, <?= (int) $m['fail'] ?> fail, <?= (int) $m['not_run'] + (int) $m['blocked'] ?> not run or blocked">
                    <span class="p" data-pct="<?= e(number_format($m['pass'] / max(1, $m['cases']) * 100, 2, '.', '')) ?>"></span>
                    <span class="f" data-pct="<?= e(number_format($m['fail'] / max(1, $m['cases']) * 100, 2, '.', '')) ?>"></span>
                    <span class="b" data-pct="<?= e(number_format(($m['blocked'] + $m['not_run']) / max(1, $m['cases']) * 100, 2, '.', '')) ?>"></span>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div class="stack">
    <section class="panel" aria-labelledby="runs-title">
      <div class="panel__head"><h2 id="runs-title">Most recent runs</h2><a href="<?= e(url('/qa/runs')) ?>">History (<?= (int) $summary['run_count'] ?>)</a></div>
      <ul class="feed">
        <?php foreach ($summary['last_runs'] as $type => $run): ?>
          <li>
            <span class="tag"><?= e($runLabels[$type]) ?></span>
            <?php if ($run === null): ?>
              <span class="muted">Not executed yet</span>
            <?php else: ?>
              <span><a href="<?= e(url('/qa/runs/' . (int) $run['id'])) ?>">Run #<?= (int) $run['id'] ?></a> · <?= e(fmt_datetime($run['started_at'])) ?> · <strong><?= (int) $run['passed'] ?>/<?= (int) $run['total'] ?></strong> passed<?= (int) $run['failed'] > 0 ? ', <span class="outcome outcome--positive">' . (int) $run['failed'] . ' failed</span>' : '' ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="panel" aria-labelledby="fail-title">
      <div class="panel__head"><h2 id="fail-title">Recent failures</h2></div>
      <?php if ($summary['recent_failures'] === []): ?>
        <p class="muted mb-0">No failed executions recorded.</p>
      <?php else: ?>
        <ul class="feed">
          <?php foreach ($summary['recent_failures'] as $f): ?>
            <li><time datetime="<?= e(iso_datetime($f['executed_at'])) ?>"><?= e(fmt_date($f['executed_at'], 'j M H:i')) ?></time>
              <span><?php if ($f['case_id']): ?><a href="<?= e(url('/qa/cases/' . (int) $f['case_id'])) ?>"><?= e($f['case_code']) ?></a><?php else: ?><?= e($f['test_name']) ?><?php endif; ?> <span class="muted">· <?= e(mb_strimwidth((string) $f['actual_result'], 0, 90, '…')) ?></span></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
