<?php $runTypes = ['regex_suite' => 'Regex suite', 'manual' => 'Manual execution', 'phpunit' => 'PHPUnit import']; ?>
<div class="app-head">
  <div>
    <p class="eyebrow">STQA laboratory</p>
    <h1>Execution history</h1>
    <p>Every run with its real start time, environment and outcome counts.</p>
  </div>
  <form method="post" action="<?= e(url('/qa/runs')) ?>" data-once>
    <?= csrf_field() ?>
    <button class="btn btn--primary" type="submit" data-busy-label="Running…">Run regex suite</button>
  </form>
</div>
<?php if ($list['rows'] === []): ?>
  <div class="empty"><h2>No executions yet</h2><p>Run the regex suite, record a manual execution, or import a PHPUnit report with <code>php bin/qa.php import-junit build/junit.xml</code>.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">Run</th><th scope="col">Type</th><th scope="col">Started</th><th scope="col">By</th><th scope="col" class="num">Total</th><th scope="col" class="num">Pass</th><th scope="col" class="num">Fail</th><th scope="col" class="num">Blocked / skipped</th><th scope="col">Environment</th></tr></thead>
      <tbody>
        <?php foreach ($list['rows'] as $r): ?>
          <tr>
            <td data-label="Run"><a href="<?= e(url('/qa/runs/' . (int) $r['id'])) ?>">#<?= (int) $r['id'] ?></a></td>
            <td data-label="Type"><?= e($runTypes[$r['run_type']] ?? $r['run_type']) ?> <span class="tag"><?= e($r['triggered_from']) ?></span></td>
            <td data-label="Started" class="small nowrap"><?= e(fmt_datetime($r['started_at'])) ?></td>
            <td data-label="By" class="small"><?= e($r['executed_by'] ?? ($r['triggered_from'] === 'cli' ? 'Command line' : '—')) ?></td>
            <td data-label="Total" class="num"><?= (int) $r['total'] ?></td>
            <td data-label="Pass" class="num"><?= (int) $r['passed'] ?></td>
            <td data-label="Fail" class="num"><?= (int) $r['failed'] > 0 ? '<strong class="outcome outcome--positive">' . (int) $r['failed'] . '</strong>' : '0' ?></td>
            <td data-label="Blocked" class="num"><?= (int) $r['blocked'] ?></td>
            <td data-label="Environment" class="small muted"><?= e($r['environment']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $view->partial('pagination', ['total' => $list['total'], 'page' => $page, 'perPage' => 20, 'path' => '/qa/runs', 'query' => []]) ?>
<?php endif; ?>
