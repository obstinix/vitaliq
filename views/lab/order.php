<?php
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Support\Labels;

$o = $order;
$me = (int) app()->auth()->id();
$open = in_array($o['status'], ['ordered', 'in_progress'], true);
$fmtRange = static function (array $i): string {
    if ($i['ref_low'] === null && $i['ref_high'] === null) {
        return '—';
    }
    $d = (int) $i['decimals'];
    return ($i['ref_low'] !== null ? number_format((float) $i['ref_low'], $d) : '') . ' – ' . ($i['ref_high'] !== null ? number_format((float) $i['ref_high'], $d) : '');
};
?>
<div class="app-head">
  <div>
    <h1>Order <span class="ref"><?= e($o['order_no']) ?></span></h1>
    <p><?= e($o['full_name']) ?> · <?= (int) $age ?> y · <?= e(Labels::get(Labels::SEX, $o['sex'])) ?> · ordered <?= e(fmt_datetime($o['created_at'])) ?> by <?= e($o['ordered_by_name'] ?? '—') ?><?= $o['request_reference'] ? ' · from ' . e($o['request_reference']) : '' ?></p>
  </div>
  <div class="cluster">
    <?= $o['priority'] !== 'routine' ? '<span class="status status--critical">' . e(LaboratoryService::PRIORITIES[$o['priority']]) . '</span>' : '' ?>
    <span class="status status--<?= e($o['status']) ?>"><?= e(LaboratoryService::ORDER_STATUSES[$o['status']]) ?></span>
    <?php if ($o['status'] === 'verified' && $canVerify): ?>
      <form method="post" action="<?= e(url('/lab/orders/' . (int) $o['id'] . '/release')) ?>"><?= csrf_field() ?><button class="btn btn--primary" type="submit">Release to patient</button></form>
    <?php endif; ?>
  </div>
</div>
<?php if ($o['clinical_notes']): ?><p class="callout callout--note"><?= e($o['clinical_notes']) ?></p><?php endif; ?>

<section class="panel" aria-labelledby="smp">
  <div class="panel__head"><h2 id="smp">Samples</h2></div>
  <ul class="rows">
    <?php foreach ($o['samples'] as $s): ?>
      <li><span class="grow"><a class="ref" href="<?= e(url('/lab/samples/' . (int) $s['id'])) ?>"><?= e($s['barcode']) ?></a><span class="sub"><?= e(LaboratoryService::SPECIMENS[$s['specimen']]) ?><?= $s['replaces_sample_id'] ? ' · recollection' : '' ?></span></span>
        <span class="status status--<?= e($s['status']) ?>"><?= e(LaboratoryService::SAMPLE_STATUSES[$s['status']]) ?></span>
        <a class="btn btn--ghost btn--small" href="<?= e(url('/lab/samples/' . (int) $s['id'] . '/label')) ?>" target="_blank" rel="noopener">Label</a></li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="panel" aria-labelledby="res">
  <div class="panel__head"><h2 id="res">Results</h2><span class="meta">Entered results need a second person to verify them</span></div>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">Test</th><th scope="col" class="num">Result</th><th scope="col">Reference</th><th scope="col">Flag</th><th scope="col">State</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>
        <?php foreach ($o['items'] as $i): ?>
          <tr id="item-<?= (int) $i['id'] ?>">
            <td data-label="Test"><?= e($i['name']) ?><span class="sub"><?= e($i['code']) ?> · <span class="ref"><?= e($i['barcode'] ?? '—') ?></span></span><?php if ($i['comment']): ?><span class="sub"><?= e($i['comment']) ?></span><?php endif; ?></td>
            <td data-label="Result" class="num"><?= e(LaboratoryService::formatValue($i)) ?> <span class="faint"><?= e($i['unit'] ?? '') ?></span></td>
            <td data-label="Reference"><?= e($fmtRange($i)) ?></td>
            <td data-label="Flag"><?php if ($i['flag'] !== null): ?><span class="flag flag--<?= e(str_starts_with((string) $i['flag'], 'critical') ? 'critical' : (string) $i['flag']) ?>"><?= e(LaboratoryService::FLAGS[$i['flag']]) ?></span><?php else: ?><span class="faint">—</span><?php endif; ?></td>
            <td data-label="State"><span class="status status--<?= e($i['status']) ?>"><?= e(ucfirst((string) $i['status'])) ?></span>
              <span class="sub"><?= $i['entered_by_name'] ? 'Entered by ' . e($i['entered_by_name']) : '' ?><?= $i['verified_by_name'] ? ' · verified by ' . e($i['verified_by_name']) : '' ?></span></td>
            <td data-label="Actions">
              <?php if ($open && in_array($i['status'], ['pending', 'entered'], true) && in_array($i['sample_status'], ['received', 'in_analysis'], true)): ?>
                <form method="post" action="<?= e(url('/lab/items/' . (int) $i['id'] . '/result')) ?>" class="cluster">
                  <?= csrf_field() ?>
                  <label class="visually-hidden" for="v-<?= (int) $i['id'] ?>">Result for <?= e($i['name']) ?></label>
                  <?php if ($i['result_type'] === 'qualitative'): ?>
                    <select class="input" id="v-<?= (int) $i['id'] ?>" name="value"><?php foreach (explode('|', (string) $i['options']) as $opt): ?><option<?= selected((string) $i['value_text'], $opt) ?>><?= e($opt) ?></option><?php endforeach; ?></select>
                  <?php else: ?>
                    <input class="input input--mono" id="v-<?= (int) $i['id'] ?>" name="value" inputmode="decimal" size="8" value="<?= e($i['value_numeric'] !== null ? LaboratoryService::formatValue($i) : '') ?>">
                  <?php endif; ?>
                  <input class="input" name="comment" maxlength="300" placeholder="Comment" aria-label="Comment">
                  <button class="btn btn--secondary btn--small" type="submit"><?= $i['status'] === 'entered' ? 'Correct' : 'Save' ?></button>
                </form>
              <?php elseif ($open && $i['status'] === 'pending'): ?>
                <span class="hint">Waiting for the sample</span>
              <?php endif; ?>
              <?php if ($i['status'] === 'entered' && $canVerify): ?>
                <?php if ((int) $i['entered_by_user_id'] !== $me): ?>
                  <div class="cluster mt-4">
                    <form method="post" action="<?= e(url('/lab/items/' . (int) $i['id'] . '/verify')) ?>"><?= csrf_field() ?><button class="btn btn--primary btn--small" type="submit">Verify</button></form>
                    <details class="confirm"><summary>Send back</summary><div class="confirm__body">
                      <form method="post" action="<?= e(url('/lab/items/' . (int) $i['id'] . '/return')) ?>"><?= csrf_field() ?><input class="input" name="reason" maxlength="280" placeholder="Why" aria-label="Reason" required><button class="btn btn--secondary btn--small mt-4" type="submit">Send back</button></form>
                    </div></details>
                  </div>
                <?php else: ?>
                  <p class="hint">You entered this result; a colleague verifies it.</p>
                <?php endif; ?>
              <?php endif; ?>
              <?php if ($open && in_array($i['status'], ['pending', 'entered'], true)): ?>
                <details class="confirm confirm--danger mt-4"><summary>Not reportable</summary><div class="confirm__body">
                  <form method="post" action="<?= e(url('/lab/items/' . (int) $i['id'] . '/reject')) ?>"><?= csrf_field() ?><input class="input" name="reason" maxlength="280" placeholder="Why" aria-label="Reason" required><button class="btn btn--danger btn--small mt-4" type="submit">Mark not reportable</button></form>
                </div></details>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($open): ?>
  <details class="confirm confirm--danger">
    <summary>Cancel this order</summary>
    <div class="confirm__body">
      <form method="post" action="<?= e(url('/lab/orders/' . (int) $o['id'] . '/cancel')) ?>"><?= csrf_field() ?>
        <div class="field"><label for="f-cr">Reason</label><input class="input" id="f-cr" name="reason" maxlength="200" required></div>
        <button class="btn btn--danger mt-4" type="submit">Cancel order <?= e($o['order_no']) ?></button>
      </form>
    </div>
  </details>
<?php endif; ?>
