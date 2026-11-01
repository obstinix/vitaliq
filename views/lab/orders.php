<?php
use Vitaliq\Services\LaboratoryService;
?>
<div class="app-head">
  <div>
    <h1>Test orders</h1>
    <p><?= (int) $data['total'] ?> <?= $status !== '' && isset(LaboratoryService::ORDER_STATUSES[$status]) ? strtolower(LaboratoryService::ORDER_STATUSES[$status]) : '' ?> orders<?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?>. STAT and urgent orders are listed first.</p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/lab/orders/new')) ?>">New test order</a>
</div>
<form class="filter-bar" method="get" action="<?= e(url('/lab/orders')) ?>">
  <div class="field"><label for="f-q">Order, patient or barcode</label><input class="input" id="f-q" name="q" value="<?= e($q) ?>" maxlength="60"></div>
  <div class="field"><label for="f-status">Status</label>
    <select class="input" id="f-status" name="status"><option value="">Any</option>
      <?php foreach (LaboratoryService::ORDER_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($status, $k) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></div>
  <div class="filter-bar__actions"><button class="btn btn--secondary" type="submit">Filter</button></div>
</form>
<?php if ($data['rows'] === []): ?>
  <p class="empty">No orders match.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">Order</th><th scope="col">Patient</th><th scope="col">Priority</th><th scope="col" class="num">Tests done</th><th scope="col">Status</th><th scope="col" class="num">Ordered</th></tr></thead>
      <tbody>
        <?php foreach ($data['rows'] as $o): ?>
          <tr>
            <td data-label="Order"><a class="ref" href="<?= e(url('/lab/orders/' . (int) $o['id'])) ?>"><?= e($o['order_no']) ?></a></td>
            <td data-label="Patient"><?= e($o['full_name']) ?></td>
            <td data-label="Priority"><?= $o['priority'] === 'routine' ? 'Routine' : '<span class="status status--critical">' . e(LaboratoryService::PRIORITIES[$o['priority']]) . '</span>' ?></td>
            <td data-label="Tests done" class="num"><?= (int) $o['done'] ?> of <?= (int) $o['tests'] ?><?= (int) $o['critical'] > 0 ? '<span class="sub flag flag--high">' . (int) $o['critical'] . ' critical</span>' : '' ?></td>
            <td data-label="Status"><span class="status status--<?= e($o['status']) ?>"><?= e(LaboratoryService::ORDER_STATUSES[$o['status']]) ?></span></td>
            <td data-label="Ordered" class="num"><?= e(fmt_datetime($o['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $view->partial('pagination', ['page' => $page, 'total' => $data['total'], 'perPage' => 25, 'path' => '/lab/orders', 'query' => ['q' => $q, 'status' => $status]]) ?>
<?php endif; ?>
