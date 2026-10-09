<?php
$errors = $errors ?? [];
if ($errors === []) {
    return;
}
?>
<div class="callout callout--urgent form-error-summary" role="alert" tabindex="-1" data-error-summary>
  <h2>Please check <?= count($errors) === 1 ? 'one detail' : count($errors) . ' details' ?></h2>
  <ul>
    <?php foreach ($errors as $field => $message): ?>
      <li><?php if ($field !== 'form'): ?><a href="#f-<?= e($field) ?>"><?= e($message) ?></a><?php else: ?><?= e($message) ?><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</div>
