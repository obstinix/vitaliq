<?php
use Vitaliq\Support\Labels;

$tabs = ['submitted', 'assigned', 'sample_collected', 'in_progress', 'completed', 'result_available', 'cancelled', 'rejected'];
$query = ['status' => $tab, 'reference' => $filters['reference'], 'name' => $filters['name']];
?>
<div class="app-head">
  <div>
    <p class="eyebrow">Operations</p>
    <h1>Testing requests</h1>
    <p>Work through each stage. Open a request to assign, record, release or reject it.</p>
  </div>
  <a class="btn btn--secondary" href="<?= e(url('/admin/reports')) ?>">Advanced search and export</a>
</div>

<nav class="tabs" aria-label="Status">
  <?php foreach ($tabs as $s): ?>
    <a href="<?= e(url('/admin/requests', ['status' => $s])) ?>"<?= $tab === $s ? ' aria-current="page"' : '' ?>><?= e(Labels::STATUSES[$s]) ?> <span class="count"><?= (int) $counts[$s] ?></span></a>
  <?php endforeach; ?>
  <a href="<?= e(url('/admin/requests', ['status' => 'all'])) ?>"<?= $tab === 'all' ? ' aria-current="page"' : '' ?>>All <span class="count"><?= (int) array_sum($counts) ?></span></a>
</nav>

<form class="filter-bar" method="get" action="<?= e(url('/admin/requests')) ?>" role="search">
  <input type="hidden" name="status" value="<?= e($tab) ?>">
  <div class="field">
    <label for="q-reference">Reference</label>
    <input class="input input--mono" id="q-reference" name="reference" maxlength="15" value="<?= e($filters['reference']) ?>">
  </div>
  <div class="field">
    <label for="q-name">Patient name</label>
    <input class="input" id="q-name" name="name" maxlength="100" value="<?= e($filters['name']) ?>">
  </div>
  <div class="filter-bar__actions">
    <button class="btn btn--primary btn--small" type="submit">Search</button>
    <a class="btn btn--ghost btn--small" href="<?= e(url('/admin/requests', ['status' => $tab])) ?>">Clear</a>
  </div>
</form>

<?php if ($result['rows'] === []): ?>
  <div class="empty">
    <h2><?= $tab === 'submitted' ? 'The queue is empty' : 'No matching requests' ?></h2>
    <p><?= $tab === 'submitted' ? 'New requests from patients will appear here.' : 'Try another status or clear the search.' ?></p>
  </div>
<?php else: ?>
  <?= $view->partial('admin-requests-table', ['rows' => $result['rows']]) ?>
  <?= $view->partial('pagination', ['total' => $result['total'], 'page' => $result['page'], 'perPage' => $result['per_page'], 'path' => '/admin/requests', 'query' => $query]) ?>
<?php endif; ?>
