<section class="wrap section">
  <p class="eyebrow"><span class="num"><?= (int) $status ?></span> <?= e($status === 404 ? 'Not found' : 'Error') ?></p>
  <h1><?= e($title) ?></h1>
  <p class="lede"><?= e($message) ?></p>
  <div class="cluster mt-5">
    <a class="btn btn--primary" href="<?= e(url('/')) ?>">Go to the homepage</a>
    <?php if ($status === 403 || $status === 419): ?>
      <a class="btn btn--secondary" href="<?= e(url('/login')) ?>">Sign in</a>
    <?php endif; ?>
  </div>
  <?php if (!empty($requestId) && $status >= 500): ?>
    <p class="hint mt-5">Reference for support: <span class="ref"><?= e($requestId) ?></span></p>
  <?php endif; ?>
</section>
