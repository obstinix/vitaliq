<?php
/** Status history timeline. Expects $history; $showActors for staff views. */
use Vitaliq\Support\Labels;

$showActors = $showActors ?? false;
?>
<ol class="timeline">
  <?php foreach ($history as $h): ?>
    <li>
      <time datetime="<?= e(iso_datetime($h['created_at'])) ?>"><?= e(fmt_datetime($h['created_at'])) ?></time>
      <strong><?= e(status_label($h['to_status'])) ?></strong>
      <?php if (!empty($h['note'])): ?><div><?= e($h['note']) ?></div><?php endif; ?>
      <?php if ($showActors): ?>
        <div class="who"><?= e($h['actor_name'] ?? ucfirst($h['actor_role'])) ?> · <?= e(Labels::get(Labels::ROLES, $h['actor_role'])) ?></div>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ol>
