<?php
/**
 * Tabs for the profiles an account holder manages. Expects $profiles,
 * $profile and $base (the page path).
 */
if (count($profiles) < 2) {
    return;
}
?>
<nav class="tabs" aria-label="Whose record">
  <?php foreach ($profiles as $p): ?>
    <?php $self = $p['rel'] === 'self'; ?>
    <a href="<?= e(url($base, $self ? [] : ['for' => (int) $p['id']])) ?>"<?= (int) $p['id'] === (int) $profile['id'] ? ' aria-current="page"' : '' ?>>
      <?= e($self ? 'You' : $p['full_name']) ?>
    </a>
  <?php endforeach; ?>
</nav>
