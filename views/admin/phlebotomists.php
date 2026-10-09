<div class="app-head">
  <div>
    <p class="eyebrow">Operations</p>
    <h1>Phlebotomists</h1>
    <p>Staff who collect samples. Records used in testing history are archived rather than deleted.</p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/admin/phlebotomists/new')) ?>">Add phlebotomist</a>
</div>

<form class="filter-bar" method="get" action="<?= e(url('/admin/phlebotomists')) ?>" role="search">
  <div class="field">
    <label for="p-q">Name, code or city</label>
    <input class="input" id="p-q" name="q" maxlength="80" value="<?= e($search) ?>">
  </div>
  <div class="field">
    <label for="p-status">Status</label>
    <select class="input" id="p-status" name="status">
      <?php foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($status, $k) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="filter-bar__actions"><button class="btn btn--primary btn--small" type="submit">Filter</button></div>
</form>

<?php if ($list['rows'] === []): ?>
  <div class="empty"><h2>No phlebotomists found</h2><p>Add the people who collect samples so requests can be assigned.</p><a class="btn btn--secondary" href="<?= e(url('/admin/phlebotomists/new')) ?>">Add phlebotomist</a></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">Code</th><th scope="col">Name</th><th scope="col">Base</th><th scope="col">Mobile</th><th scope="col" class="num">Open</th><th scope="col" class="num">Collected</th><th scope="col">Portal login</th><th scope="col">Status</th></tr></thead>
      <tbody>
        <?php foreach ($list['rows'] as $p): ?>
          <tr>
            <td data-label="Code" class="mono"><?= e($p['employee_code']) ?></td>
            <td data-label="Name"><a href="<?= e(url('/admin/phlebotomists/' . (int) $p['id'])) ?>"><?= e($p['full_name']) ?></a></td>
            <td data-label="Base"><?= e($p['base_city']) ?>, <?= e($p['state_name']) ?></td>
            <td data-label="Mobile" class="mono"><?= e(format_mobile($p['mobile'])) ?></td>
            <td data-label="Open" class="num"><?= (int) $p['open_collections'] ?></td>
            <td data-label="Collected" class="num"><?= (int) $p['total_collections'] ?></td>
            <td data-label="Login"><?= $p['login_email'] ? e($p['login_email']) : '<span class="faint">None</span>' ?></td>
            <td data-label="Status"><span class="status status--<?= (int) $p['is_active'] === 1 ? 'pass' : 'blocked' ?>"><?= (int) $p['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $view->partial('pagination', ['total' => $list['total'], 'page' => $page, 'perPage' => 20, 'path' => '/admin/phlebotomists', 'query' => ['q' => $search, 'status' => $status]]) ?>
<?php endif; ?>
