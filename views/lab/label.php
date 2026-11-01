<?php
use Vitaliq\Services\LaboratoryService;

$s = $sample;
?>
<div class="print-actions no-print"><button class="btn btn--primary btn--small" type="button" data-print>Print label</button></div>
<div class="sample-label">
  <div class="sample-label__qr"><?= $qr ?></div>
  <div>
    <p class="sample-label__code"><?= e(implode(' ', str_split((string) $s['barcode'], 4))) ?></p>
    <p><strong><?= e($s['full_name']) ?></strong> · <?= e(fmt_date($s['date_of_birth'], 'Y-m-d')) ?></p>
    <p><?= e(LaboratoryService::SPECIMENS[$s['specimen']]) ?> · <?= e($s['order_no']) ?><?= $s['priority'] !== 'routine' ? ' · ' . e(LaboratoryService::PRIORITIES[$s['priority']]) : '' ?></p>
  </div>
</div>
