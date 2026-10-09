<?php
use Vitaliq\Support\Labels;

$f = $filters;
?>
<div class="app-head">
  <div>
    <p class="eyebrow">STQA laboratory</p>
    <h1>Defect register</h1>
    <p>Each defect states where it came from: found during development testing, reported in this lab, or a clearly labelled demonstration record.</p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/qa/defects/new')) ?>">Report a defect</a>
</div>
<form class="filter-bar" method="get" action="<?= e(url('/qa/defects')) ?>">
  <div class="field">
    <label for="d-status">Status</label>
    <select class="input" id="d-status" name="status"><option value="">Any</option><?php foreach (Labels::DEFECT_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
  </div>
  <div class="field">
    <label for="d-severity">Severity</label>
    <select class="input" id="d-severity" name="severity"><option value="">Any</option><?php foreach (Labels::SEVERITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['severity'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
  </div>
  <div class="field">
    <label for="d-origin">Origin</label>
    <select class="input" id="d-origin" name="origin"><option value="">Any</option><?php foreach (Labels::DEFECT_ORIGINS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['origin'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
  </div>
  <div class="filter-bar__actions"><button class="btn btn--primary btn--small" type="submit">Filter</button></div>
</form>
<?php if ($list === []): ?>
  <div class="empty"><h2>No defects</h2><p>Nothing matches these filters.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">ID</th><th scope="col">Title</th><th scope="col">Module</th><th scope="col">Severity</th><th scope="col">Priority</th><th scope="col">Status</th><th scope="col">Origin</th><th scope="col">Created</th></tr></thead>
      <tbody>
        <?php foreach ($list as $d): ?>
          <tr>
            <td data-label="ID"><a class="mono small" href="<?= e(url('/qa/defects/' . (int) $d['id'])) ?>"><?= e($d['code']) ?></a></td>
            <td data-label="Title"><?= e($d['title']) ?><?= (int) $d['linked_cases'] > 0 ? '<br><span class="small muted">' . (int) $d['linked_cases'] . ' linked case(s)</span>' : '' ?></td>
            <td data-label="Module"><?= e($d['module']) ?></td>
            <td data-label="Severity"><?= e(Labels::get(Labels::SEVERITIES, $d['severity'])) ?></td>
            <td data-label="Priority"><?= e(Labels::get(Labels::PRIORITIES, $d['priority'])) ?></td>
            <td data-label="Status"><span class="status status--<?= e($d['status']) ?>"><?= e(Labels::get(Labels::DEFECT_STATUSES, $d['status'])) ?></span></td>
            <td data-label="Origin"><span class="tag<?= $d['origin'] === 'demo' ? ' tag--demo' : ($d['origin'] === 'development_testing' ? ' tag--real' : '') ?>"><?= e(Labels::get(Labels::DEFECT_ORIGINS, $d['origin'])) ?></span></td>
            <td data-label="Created" class="small nowrap"><?= e(fmt_date($d['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
