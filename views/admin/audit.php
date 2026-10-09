<div class="app-head">
  <div>
    <p class="eyebrow">Governance</p>
    <h1>Audit log</h1>
    <p>Security and administrative events, newest first. Network addresses are stored only as keyed hashes.</p>
  </div>
</div>
<form class="filter-bar" method="get" action="<?= e(url('/admin/audit')) ?>" role="search">
  <div class="field">
    <label for="a-action">Action starts with</label>
    <input class="input input--mono" id="a-action" name="action" maxlength="60" placeholder="request." value="<?= e($action) ?>">
  </div>
  <div class="filter-bar__actions"><button class="btn btn--primary btn--small" type="submit">Filter</button></div>
</form>
<?php if ($list['rows'] === []): ?>
  <div class="empty"><h2>No events</h2><p>Audit events appear as people use the system.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">When</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Entity</th><th scope="col">Details</th></tr></thead>
      <tbody>
        <?php foreach ($list['rows'] as $a): ?>
          <tr>
            <td data-label="When" class="nowrap mono small"><?= e(fmt_date($a['created_at'], 'Y-m-d H:i:s')) ?></td>
            <td data-label="Actor"><?= e($a['actor_name'] ?? 'Anonymous') ?></td>
            <td data-label="Action" class="mono small"><?= e($a['action']) ?></td>
            <td data-label="Entity" class="mono small"><?= e($a['entity_type']) ?><?= $a['entity_id'] !== null ? ' #' . (int) $a['entity_id'] : '' ?></td>
            <td data-label="Details" class="mono small"><?= e($a['details'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $view->partial('pagination', ['total' => $list['total'], 'page' => $page, 'perPage' => 40, 'path' => '/admin/audit', 'query' => ['action' => $action]]) ?>
<?php endif; ?>
