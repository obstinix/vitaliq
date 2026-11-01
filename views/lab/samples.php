<?php
use Vitaliq\Services\LaboratoryService;

$c = $data['counts'];
?>
<div class="app-head">
  <div>
    <h1>Samples</h1>
    <p><?= $status !== '' && isset(LaboratoryService::SAMPLE_STATUSES[$status]) ? count($data['rows']) . ' ' . strtolower(LaboratoryService::SAMPLE_STATUSES[$status]) : array_sum($c) . ' samples in total' ?>.</p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/lab/scan')) ?>">Scan a barcode</a>
</div>
<nav class="tabs" aria-label="Sample stage">
  <a href="<?= e(url('/lab/samples')) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>All</a>
  <?php foreach (LaboratoryService::SAMPLE_STATUSES as $k => $l): ?>
    <a href="<?= e(url('/lab/samples', ['status' => $k])) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> <span class="count"><?= (int) $c[$k] ?></span></a>
  <?php endforeach; ?>
</nav>
<?php if ($data['rows'] === []): ?>
  <p class="empty">No samples in this stage.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">Barcode</th><th scope="col">Patient</th><th scope="col">Specimen</th><th scope="col">Order</th><th scope="col">Stage</th><th scope="col" class="num">Created</th></tr></thead>
      <tbody>
        <?php foreach ($data['rows'] as $s): ?>
          <tr>
            <td data-label="Barcode"><a class="ref" href="<?= e(url('/lab/samples/' . (int) $s['id'])) ?>"><?= e($s['barcode']) ?></a></td>
            <td data-label="Patient"><?= e($s['full_name']) ?></td>
            <td data-label="Specimen"><?= e(LaboratoryService::SPECIMENS[$s['specimen']]) ?></td>
            <td data-label="Order"><a href="<?= e(url('/lab/orders/' . (int) $s['order_id'])) ?>"><?= e($s['order_no']) ?></a><?= $s['priority'] !== 'routine' ? ' <span class="status status--critical">' . e(LaboratoryService::PRIORITIES[$s['priority']]) . '</span>' : '' ?></td>
            <td data-label="Stage"><span class="status status--<?= e($s['status']) ?>"><?= e(LaboratoryService::SAMPLE_STATUSES[$s['status']]) ?></span></td>
            <td data-label="Created" class="num"><?= e(fmt_datetime($s['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
