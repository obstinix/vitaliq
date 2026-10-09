<?php
use Vitaliq\Support\Labels;

$f = $filters;
$fe = $f['errors'];
?>
<div class="app-head">
  <div>
    <p class="eyebrow">Operations</p>
    <h1>Testing reports</h1>
    <p>Search and filter every request. Exports include up to <?= fmt_number(\Vitaliq\Services\ReportService::EXPORT_LIMIT) ?> rows and are recorded in the audit log.</p>
  </div>
  <a class="btn btn--secondary" href="<?= e(url('/admin/reports/export', $query)) ?>">Export CSV</a>
</div>

<form class="filter-bar" method="get" action="<?= e(url('/admin/reports')) ?>" role="search" aria-label="Filter requests">
  <div class="field">
    <label for="r-reference">Reference</label>
    <input class="input input--mono" id="r-reference" name="reference" maxlength="15" value="<?= e($f['reference']) ?>"<?= isset($fe['reference']) ? ' aria-invalid="true" aria-describedby="err-reference"' : '' ?>>
    <?= field_error($fe, 'reference') ?>
  </div>
  <div class="field">
    <label for="r-name">Patient name</label>
    <input class="input" id="r-name" name="name" maxlength="100" value="<?= e($f['name']) ?>">
  </div>
  <div class="field">
    <label for="r-mobile">Mobile number</label>
    <input class="input input--mono" id="r-mobile" name="mobile" inputmode="tel" maxlength="16" value="<?= e($f['mobile']) ?>"<?= isset($fe['mobile']) ? ' aria-invalid="true" aria-describedby="err-mobile"' : '' ?>>
    <?= field_error($fe, 'mobile') ?>
  </div>
  <div class="field">
    <label for="r-from">Submitted from</label>
    <input class="input" id="r-from" name="from" type="date" value="<?= e($f['from']) ?>"<?= isset($fe['from']) ? ' aria-invalid="true" aria-describedby="err-from"' : '' ?>>
    <?= field_error($fe, 'from') ?>
  </div>
  <div class="field">
    <label for="r-to">Submitted to</label>
    <input class="input" id="r-to" name="to" type="date" value="<?= e($f['to']) ?>"<?= isset($fe['to']) ? ' aria-invalid="true" aria-describedby="err-to"' : '' ?>>
    <?= field_error($fe, 'to') ?>
  </div>
  <div class="field">
    <label for="r-status">Status</label>
    <select class="input" id="r-status" name="status">
      <option value="">Any status</option>
      <?php foreach (Labels::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="r-phleb">Phlebotomist</label>
    <select class="input" id="r-phleb" name="phlebotomist">
      <option value="">Anyone</option>
      <?php foreach ($phlebotomists as $id => $label): ?><option value="<?= (int) $id ?>"<?= selected((string) $f['phlebotomist'], (string) $id) ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="r-type">Test type</label>
    <select class="input" id="r-type" name="test_type">
      <option value="">Any test</option>
      <?php foreach (Labels::TEST_TYPES_SHORT as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($f['test_type'], $k) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="r-state">State / UT</label>
    <select class="input" id="r-state" name="state">
      <option value="">Anywhere</option>
      <?php foreach ($states as $st): ?><option value="<?= e($st['code']) ?>"<?= selected($f['state'], $st['code']) ?>><?= e($st['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="r-sort">Sort by</label>
    <select class="input" id="r-sort" name="sort">
      <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'preferred' => 'Preferred date', 'reference' => 'Reference', 'patient' => 'Patient name'] as $k => $l): ?>
        <option value="<?= e($k) ?>"<?= selected($f['sort'], $k) ?>><?= e($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-bar__actions">
    <button class="btn btn--primary btn--small" type="submit">Apply</button>
    <a class="btn btn--ghost btn--small" href="<?= e(url('/admin/reports')) ?>">Reset</a>
  </div>
</form>

<section aria-labelledby="summary-title" class="mb-5">
  <h2 id="summary-title" class="visually-hidden">Matching requests by status</h2>
  <nav class="pipeline" aria-label="Matching requests by status">
    <?php foreach ($summary as $status => $n): ?>
      <a href="<?= e(url('/admin/reports', array_merge($query, ['status' => $status, 'page' => null]))) ?>">
        <span class="n"><?= fmt_number($n) ?></span><span class="l"><?= e(Labels::STATUSES[$status]) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
</section>

<p class="muted" aria-live="polite"><?= fmt_number($result['total']) ?> matching request<?= $result['total'] === 1 ? '' : 's' ?>.</p>
<?php if ($result['rows'] === []): ?>
  <div class="empty"><h2>No requests match these filters</h2><p>Widen the date range or clear some filters.</p></div>
<?php else: ?>
  <?= $view->partial('admin-requests-table', ['rows' => $result['rows']]) ?>
  <?= $view->partial('pagination', ['total' => $result['total'], 'page' => $result['page'], 'perPage' => $result['per_page'], 'path' => '/admin/reports', 'query' => $query]) ?>
<?php endif; ?>
