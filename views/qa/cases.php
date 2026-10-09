<?php
use Vitaliq\Support\Labels;

$f = $filters;
?>
<div class="app-head">
  <div>
    <p class="eyebrow">STQA laboratory</p>
    <h1>Test case catalogue</h1>
    <p>Definitions from the catalogue, with the latest recorded execution of each. Expected results are written before execution; actual results come only from runs.</p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/qa/cases/new')) ?>">Add test case</a>
</div>

<form class="filter-bar" method="get" action="<?= e(url('/qa/cases')) ?>" role="search">
  <div class="field">
    <label for="c-q">Code or title</label>
    <input class="input" id="c-q" name="q" maxlength="80" value="<?= e($f['q']) ?>">
  </div>
  <div class="field">
    <label for="c-module">Module</label>
    <select class="input" id="c-module" name="module">
      <option value="">All modules</option>
      <?php foreach ($modules as $m): ?><option value="<?= e($m) ?>"<?= selected($f['module'], $m) ?>><?= e($m) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="c-kind">Kind</label>
    <select class="input" id="c-kind" name="kind">
      <option value="">Any</option>
      <option value="regex"<?= selected($f['kind'], 'regex') ?>>Regex (automated)</option>
      <option value="manual"<?= selected($f['kind'], 'manual') ?>>Manual</option>
    </select>
  </div>
  <div class="field">
    <label for="c-type">Case type</label>
    <select class="input" id="c-type" name="type">
      <option value="">Any</option>
      <?php foreach (Labels::CASE_TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['type'], $k) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="c-status">Latest result</label>
    <select class="input" id="c-status" name="status">
      <option value="">Any</option>
      <?php foreach (['pass' => 'Pass', 'fail' => 'Fail', 'blocked' => 'Blocked', 'not_run' => 'Not run'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="filter-bar__actions">
    <button class="btn btn--primary btn--small" type="submit">Filter</button>
    <a class="btn btn--ghost btn--small" href="<?= e(url('/qa/cases')) ?>">Reset</a>
  </div>
</form>

<?php if ($list['rows'] === []): ?>
  <div class="empty"><h2>No cases match</h2><p>Change the filters, or load the catalogue with <code>php bin/qa.php sync</code>.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">ID</th><th scope="col">Module</th><th scope="col">Title</th><th scope="col">Input</th><th scope="col">Expected</th><th scope="col">Actual (latest)</th><th scope="col">Status</th><th scope="col">Executed</th><th scope="col">Defect</th></tr></thead>
      <tbody>
        <?php foreach ($list['rows'] as $c): ?>
          <tr>
            <td data-label="ID" class="nowrap"><a class="mono small" href="<?= e(url('/qa/cases/' . (int) $c['id'])) ?>"><?= e($c['case_code']) ?></a><br><span class="tag"><?= e(Labels::get(Labels::CASE_TYPES, $c['case_type'])) ?></span><?= $c['origin'] === 'user' ? ' <span class="tag">Lab-added</span>' : '' ?></td>
            <td data-label="Module"><?= e($c['module']) ?></td>
            <td data-label="Title"><?= e($c['title']) ?><?= $c['rule_key'] ? '<br><span class="small muted mono">' . e($c['rule_key']) . '</span>' : '' ?></td>
            <td data-label="Input" class="mono small"><?= $c['kind'] === 'regex' ? e(show_input($c['input_value'])) : e(mb_strimwidth((string) $c['input_value'], 0, 80, '…')) ?></td>
            <td data-label="Expected" class="small"><?= e($c['expected_result']) ?></td>
            <td data-label="Actual" class="small"><?= $c['last_actual'] !== null ? e(mb_strimwidth((string) $c['last_actual'], 0, 90, '…')) : '<span class="faint">Not executed</span>' ?></td>
            <td data-label="Status"><span class="status status--<?= e($c['last_status'] ?? 'not_run') ?>"><?= e($c['last_status'] ? ucfirst($c['last_status']) : 'Not run') ?></span></td>
            <td data-label="Executed" class="small nowrap"><?= $c['last_executed_at'] ? '<a href="' . e(url('/qa/runs/' . (int) $c['last_run_id'])) . '">' . e(fmt_date($c['last_executed_at'], 'j M H:i')) . '</a>' : '—' ?></td>
            <td data-label="Defect"><?= $c['defect_code'] ? '<a class="mono small" href="' . e(url('/qa/defects/' . (int) $c['defect_id'])) . '">' . e($c['defect_code']) . '</a>' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $view->partial('pagination', ['total' => $list['total'], 'page' => $page, 'perPage' => 40, 'path' => '/qa/cases', 'query' => $f]) ?>
<?php endif; ?>
