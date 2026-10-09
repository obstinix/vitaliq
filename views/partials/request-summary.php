<?php
/** Key facts about a request, for patient-facing views. Expects $request. */
use Vitaliq\Support\Labels;
?>
<dl class="details">
  <dt>Reference</dt><dd><span class="ref"><?= e($request['reference']) ?></span></dd>
  <dt>Patient</dt><dd><?= e($request['full_name']) ?></dd>
  <dt>Test</dt><dd><?= e(Labels::get(Labels::TEST_TYPES, $request['test_type'])) ?></dd>
  <dt>Reason</dt><dd><?= e(Labels::get(Labels::REASONS, $request['reason'])) ?></dd>
  <?php if (!empty($request['symptoms'])): ?>
    <dt>Symptoms</dt><dd><?= e(implode(', ', array_map(static fn ($s) => Labels::get(Labels::SYMPTOMS, $s), $request['symptoms']))) ?><?= $request['symptom_onset'] ? ' · since ' . e(fmt_date($request['symptom_onset'])) : '' ?></dd>
  <?php endif; ?>
  <dt>Collection</dt><dd><?= e(Labels::get(Labels::COLLECTION_MODES, $request['collection_mode'])) ?>, <?= e($request['city']) ?>, <?= e($request['state_name']) ?></dd>
  <dt>Preferred</dt><dd><?= e(fmt_date($request['preferred_date'], 'l j M Y')) ?> · <?= e(Labels::get(Labels::SLOTS, $request['preferred_slot'])) ?></dd>
  <dt>Submitted</dt><dd><time datetime="<?= e(iso_datetime($request['submitted_at'])) ?>"><?= e(fmt_datetime($request['submitted_at'])) ?></time></dd>
  <?php if (!empty($request['assignment'])): ?>
    <dt>Phlebotomist</dt><dd><?= e($request['assignment']['phlebotomist_name']) ?></dd>
  <?php endif; ?>
  <?php if (!empty($request['sample'])): ?>
    <dt>Sample</dt><dd><?= e(Labels::get(Labels::SPECIMENS, $request['sample']['specimen'])) ?> · collected <?= e(fmt_datetime($request['sample']['collected_at'])) ?></dd>
  <?php endif; ?>
</dl>
