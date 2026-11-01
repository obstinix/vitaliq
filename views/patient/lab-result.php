<?php
use Vitaliq\Services\LaboratoryService;
?>
<div class="app-head">
  <div>
    <h1>Lab report <?= e($order['order_no']) ?></h1>
    <p><?= e($order['full_name']) ?> · released <?= e(fmt_datetime($order['released_at'])) ?></p>
  </div>
  <button class="btn btn--secondary" type="button" data-print>Print</button>
</div>
<section class="panel">
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">Test</th><th scope="col" class="num">Result</th><th scope="col">Unit</th><th scope="col">Reference range</th><th scope="col">Flag</th></tr></thead>
      <tbody>
        <?php foreach ($order['items'] as $i): ?>
          <tr>
            <td data-label="Test"><?= e($i['name']) ?><?php if ($i['comment']): ?><span class="sub"><?= e($i['comment']) ?></span><?php endif; ?></td>
            <td data-label="Result" class="num"><strong><?= e(LaboratoryService::formatValue($i)) ?></strong></td>
            <td data-label="Unit"><?= e($i['unit'] ?? '') ?></td>
            <td data-label="Reference range"><?= $i['ref_low'] !== null || $i['ref_high'] !== null ? e(($i['ref_low'] !== null ? number_format((float) $i['ref_low'], (int) $i['decimals']) : '') . ' – ' . ($i['ref_high'] !== null ? number_format((float) $i['ref_high'], (int) $i['decimals']) : '')) : '<span class="faint">—</span>' ?></td>
            <td data-label="Flag"><span class="flag flag--<?= e(str_starts_with((string) $i['flag'], 'critical') ? 'critical' : (string) $i['flag']) ?>"><?= e(LaboratoryService::FLAGS[$i['flag']] ?? '—') ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="disclaimer">A result outside the reference range is not a diagnosis. Reference ranges vary between laboratories and people. Discuss these results with your doctor.</p>
</section>
