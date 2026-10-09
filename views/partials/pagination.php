<?php
/**
 * Expects: $total, $page, $perPage, $path, $query (current filters).
 */
$pages = max(1, (int) ceil($total / max(1, $perPage)));
if ($pages <= 1) {
    return;
}
$query = $query ?? [];
$link = static fn (int $p): string => url($path, array_merge($query, ['page' => $p]));
$window = array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], static fn ($p) => $p >= 1 && $p <= $pages));
sort($window);
?>
<nav class="pagination" aria-label="Pagination">
  <span class="pagination__summary">Page <?= (int) $page ?> of <?= (int) $pages ?> · <?= fmt_number($total) ?> records</span>
  <?php if ($page > 1): ?>
    <a href="<?= e($link($page - 1)) ?>" rel="prev">Previous</a>
  <?php else: ?>
    <span class="disabled" aria-hidden="true">Previous</span>
  <?php endif; ?>
  <?php $prev = 0; foreach ($window as $p): ?>
    <?php if ($p - $prev > 1): ?><span class="disabled" aria-hidden="true">…</span><?php endif; ?>
    <?php if ($p === $page): ?>
      <span aria-current="page"><?= (int) $p ?></span>
    <?php else: ?>
      <a href="<?= e($link($p)) ?>" aria-label="Page <?= (int) $p ?>"><?= (int) $p ?></a>
    <?php endif; ?>
  <?php $prev = $p; endforeach; ?>
  <?php if ($page < $pages): ?>
    <a href="<?= e($link($page + 1)) ?>" rel="next">Next</a>
  <?php else: ?>
    <span class="disabled" aria-hidden="true">Next</span>
  <?php endif; ?>
</nav>
