<?php
use Vitaliq\Services\LabResourcesService;

$usable = 0;
foreach ($item['lots'] as $l) {
    if ($l['expires_on'] === null || $l['expires_on'] >= $today) {
        $usable += (int) $l['quantity'];
    }
}
?>
<div class="app-head">
  <div>
    <h1><?= e($item['name']) ?></h1>
    <p><span class="ref"><?= e($item['sku']) ?></span> · <?= e(LabResourcesService::CATEGORIES[$item['category']]) ?> · <?= fmt_number($usable) ?> <?= e($item['unit']) ?> usable · reorder at <?= fmt_number($item['reorder_level']) ?></p>
  </div>
  <a href="<?= e(url('/lab/inventory')) ?>">Inventory</a>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <div class="span-8 stack">
    <section class="panel" aria-labelledby="lots">
      <div class="panel__head"><h2 id="lots">Lots</h2><span class="meta">Used earliest expiry first</span></div>
      <?php if ($item['lots'] === []): ?>
        <p class="empty">No deliveries recorded.</p>
      <?php else: ?>
        <table class="table">
          <thead><tr><th scope="col">Lot</th><th scope="col" class="num">Expires</th><th scope="col" class="num">Quantity</th><th scope="col" class="num">Received</th></tr></thead>
          <tbody>
            <?php foreach ($item['lots'] as $l): ?>
              <?php $expired = $l['expires_on'] !== null && $l['expires_on'] < $today; ?>
              <tr><td class="ref"><?= e($l['lot_no']) ?></td><td class="num"><?= $l['expires_on'] ? e($l['expires_on']) : '—' ?><?= $expired ? ' <span class="status status--critical">Expired</span>' : '' ?></td><td class="num"><?= fmt_number($l['quantity']) ?></td><td class="num"><?= e(fmt_date($l['received_at'], 'Y-m-d')) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="mov">
      <div class="panel__head"><h2 id="mov">Movements</h2><span class="meta">Last 50</span></div>
      <?php if ($item['movements'] === []): ?>
        <p class="empty">No stock movements yet.</p>
      <?php else: ?>
        <table class="table">
          <thead><tr><th scope="col">When</th><th scope="col">Reason</th><th scope="col">Lot</th><th scope="col" class="num">Change</th><th scope="col">By</th></tr></thead>
          <tbody>
            <?php foreach ($item['movements'] as $m): ?>
              <tr><td class="num"><?= e(fmt_datetime($m['created_at'])) ?></td><td><?= e(LabResourcesService::REASONS[$m['reason']]) ?><?= $m['note'] ? '<span class="sub">' . e($m['note']) . '</span>' : '' ?></td><td class="ref"><?= e($m['lot_no'] ?? '—') ?></td>
                <td class="num"><?= (int) $m['quantity_change'] > 0 ? '+' : '' ?><?= fmt_number($m['quantity_change']) ?></td><td><?= e($m['full_name'] ?? '—') ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>
  <div class="span-4 stack">
    <section class="panel" aria-labelledby="rcv">
      <div class="panel__head"><h2 id="rcv">Record a delivery</h2></div>
      <form method="post" action="<?= e(url('/lab/inventory/' . (int) $item['id'] . '/receive')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field"><label for="f-lot_no">Lot number</label><input class="input input--mono" id="f-lot_no" name="lot_no" maxlength="40" value="<?= e(old($old, 'lot_no')) ?>"<?= aria_invalid($errors, 'lot_no') ?>><?= field_error($errors, 'lot_no') ?></div>
          <div class="field"><label for="f-expires_on">Expiry <span class="optional">if printed</span></label><input class="input" type="date" id="f-expires_on" name="expires_on" min="<?= e($today) ?>" value="<?= e(old($old, 'expires_on')) ?>"<?= aria_invalid($errors, 'expires_on') ?>><?= field_error($errors, 'expires_on') ?></div>
          <div class="field"><label for="f-quantity">Quantity (<?= e($item['unit']) ?>)</label><input class="input" id="f-quantity" name="quantity" inputmode="numeric" value="<?= e(old($old, 'quantity')) ?>"<?= aria_invalid($errors, 'quantity') ?>><?= field_error($errors, 'quantity') ?></div>
        </div>
        <div class="form-actions"><button class="btn btn--primary" type="submit">Add to stock</button></div>
      </form>
    </section>
    <section class="panel" aria-labelledby="use">
      <div class="panel__head"><h2 id="use">Take out of stock</h2></div>
      <form method="post" action="<?= e(url('/lab/inventory/' . (int) $item['id'] . '/use')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field"><label for="u-quantity">Quantity</label><input class="input" id="u-quantity" name="quantity" inputmode="numeric"></div>
          <div class="field"><label for="u-reason">Reason</label><select class="input" id="u-reason" name="reason"><option value="consumption">Used</option><option value="wastage">Wasted</option><option value="adjustment">Count adjustment</option></select></div>
          <div class="field"><label for="u-note">Note <span class="optional">required unless used</span></label><input class="input" id="u-note" name="note" maxlength="200"></div>
        </div>
        <div class="form-actions"><button class="btn btn--secondary" type="submit">Take out</button></div>
      </form>
    </section>
  </div>
</div>
