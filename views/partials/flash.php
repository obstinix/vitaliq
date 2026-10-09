<?php
$flash = $flash ?? [];
$items = array_filter([
    'success' => $flash['success'] ?? null,
    'notice' => $flash['notice'] ?? null,
    'error' => $flash['error'] ?? null,
]);
if ($items === []) {
    return;
}
$icons = ['success' => '✓', 'notice' => 'i', 'error' => '!'];
?>
<div class="flash-region">
  <?php foreach ($items as $type => $message): ?>
    <div class="flash flash--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
      <span class="flash__icon" aria-hidden="true"><?= e($icons[$type]) ?></span>
      <p><?= e($message) ?></p>
    </div>
  <?php endforeach; ?>
</div>
