<?php
use Vitaliq\Services\LabResourcesService;

$states = ['out' => ['Out of stock', 'critical'], 'low' => ['Low', 'low'], 'expiring' => ['Expiring within 30 days', 'warning'], 'ok' => ['In stock', 'ok']];
$counts = array_count_values(array_column($items, 'state'));
$expired = array_sum(array_map(static fn ($i) => (int) $i['expired_qty'], $items));
?>
<div class="app-head">
  <div>
    <h1>Inventory</h1>
    <p><?= count($items) ?> items · <?= (int) ($counts['out'] ?? 0) ?> out of stock · <?= (int) ($counts['low'] ?? 0) ?> at or below reorder level · <?= $expired ?> expired units still on the shelf.</p>
  </div>
  <?php if ($expired > 0): ?>
    <form method="post" action="<?= e(url('/lab/inventory/write-off')) ?>"><?= csrf_field() ?><button class="btn btn--secondary" type="submit">Write off <?= $expired ?> expired units</button></form>
  <?php endif; ?>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="span-8">
    <?php if ($items === []): ?>
      <p class="empty">No stock items yet. Add the reagents, kits and consumables the laboratory uses.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Item</th><th scope="col">Category</th><th scope="col" class="num">Usable</th><th scope="col" class="num">Reorder at</th><th scope="col" class="num">Next expiry</th><th scope="col">State</th></tr></thead>
          <tbody>
            <?php foreach ($items as $i): ?>
              <tr>
                <td data-label="Item"><a href="<?= e(url('/lab/inventory/' . (int) $i['id'])) ?>"><?= e($i['name']) ?></a><span class="sub ref"><?= e($i['sku']) ?></span></td>
                <td data-label="Category"><?= e(LabResourcesService::CATEGORIES[$i['category']]) ?></td>
                <td data-label="Usable" class="num"><?= fmt_number($i['usable']) ?> <span class="faint"><?= e($i['unit']) ?></span></td>
                <td data-label="Reorder at" class="num"><?= fmt_number($i['reorder_level']) ?></td>
                <td data-label="Next expiry" class="num"><?= $i['next_expiry'] ? e($i['next_expiry']) : '<span class="faint">—</span>' ?></td>
                <td data-label="State"><span class="status status--<?= e($states[$i['state']][1]) ?>"><?= e($states[$i['state']][0]) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <section class="panel span-4" aria-labelledby="add-item">
    <div class="panel__head"><h2 id="add-item">Add an item</h2></div>
    <form method="post" action="<?= e(url('/lab/inventory')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label for="f-sku">SKU</label><input class="input input--mono" id="f-sku" name="sku" maxlength="24" value="<?= e(old($old, 'sku')) ?>"<?= aria_invalid($errors, 'sku') ?>><?= field_error($errors, 'sku') ?></div>
        <div class="field"><label for="f-name">Name</label><input class="input" id="f-name" name="name" maxlength="120" value="<?= e(old($old, 'name')) ?>"<?= aria_invalid($errors, 'name') ?>><?= field_error($errors, 'name') ?></div>
        <div class="field"><label for="f-category">Category</label><select class="input" id="f-category" name="category"<?= aria_invalid($errors, 'category') ?>><option value="">Choose</option><?php foreach (LabResourcesService::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'category'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'category') ?></div>
        <div class="field"><label for="f-unit">Unit</label><input class="input" id="f-unit" name="unit" maxlength="20" placeholder="tests, boxes, mL" value="<?= e(old($old, 'unit')) ?>"<?= aria_invalid($errors, 'unit') ?>><?= field_error($errors, 'unit') ?></div>
        <div class="field"><label for="f-reorder_level">Reorder level</label><input class="input" id="f-reorder_level" name="reorder_level" inputmode="numeric" value="<?= e(old($old, 'reorder_level', '0')) ?>"<?= aria_invalid($errors, 'reorder_level') ?>><?= field_error($errors, 'reorder_level') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Add item</button></div>
    </form>
  </section>
</div>
