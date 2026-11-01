<?php
use Vitaliq\Support\Labels;
?>
<section class="section">
  <div class="wrap wrap--narrow">
    <?php if (!empty($limited)): ?>
      <h1>Too many requests</h1>
      <p>Please wait a few minutes and scan the code again.</p>
    <?php elseif ($card === null): ?>
      <h1>Health ID not available</h1>
      <p>This code is not valid. It may have been revoked or may have expired.</p>
    <?php else: ?>
      <?php $p = $card['profile']; ?>
      <p class="tag">Emergency health card</p>
      <h1><?= e($card['patient']['full_name']) ?></h1>
      <p class="lead"><?= (int) $card['age'] ?> years · <?= e(Labels::get(Labels::SEX, $card['patient']['sex'])) ?></p>
      <dl class="details">
        <dt>Blood group</dt><dd><?= $p['blood_group'] === 'unknown' ? 'Not recorded' : e($p['blood_group']) ?></dd>
        <dt>Allergies</dt><dd><?= $p['allergies'] ? e($p['allergies']) : 'None recorded' ?></dd>
        <dt>Long-term conditions</dt><dd><?= $p['conditions'] ? e($p['conditions']) : 'None recorded' ?></dd>
        <dt>Current medicines</dt><dd><?= $p['medications'] ? e($p['medications']) : 'None recorded' ?></dd>
        <dt>Organ donor</dt><dd><?= e(['yes' => 'Yes', 'no' => 'No', 'undecided' => 'Not decided'][$p['organ_donor']] ?? 'Not decided') ?></dd>
      </dl>
      <h2 class="mt-4">Emergency contacts</h2>
      <?php if ($card['contacts'] === []): ?>
        <p>No emergency contact recorded.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($card['contacts'] as $c): ?>
            <li><strong><?= e($c['full_name']) ?></strong> (<?= e($c['relationship']) ?>): <a href="tel:+91<?= e($c['mobile']) ?>"><?= e(format_mobile($c['mobile'])) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="callout callout--note">This card was written by the patient or their family and has not been checked by a clinician. Confirm details before acting on them. In an emergency in India, call 112.</p>
    <?php endif; ?>
  </div>
</section>
