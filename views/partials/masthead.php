<?php
$path = $currentPath ?? '/';
$links = [
    '/covid-19' => 'COVID-19',
    '/symptoms' => 'Symptoms',
    '/testing' => 'Testing',
    '/statistics' => 'Statistics',
    '/community' => 'Community',
    '/track' => 'Track a test',
];
$user = $currentUser ?? null;
$home = $user === null ? null : match ($user['role']) {
    'admin' => '/admin', 'phlebotomist' => '/staff', 'qa' => '/qa', default => '/patient',
};
?>
<header class="masthead">
  <div class="wrap masthead__inner">
    <?= $view->partial('wordmark', ['tagline' => true]) ?>
    <button class="btn btn--secondary btn--small nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" data-nav-toggle>
      Menu
    </button>
    <nav class="primary-nav" id="primary-nav" aria-label="Main">
      <ul>
        <?php foreach ($links as $href => $label): ?>
          <li><a href="<?= e(url($href)) ?>"<?= $path === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="masthead__actions">
      <?php if ($user !== null): ?>
        <a class="btn btn--primary btn--small" href="<?= e(url($home)) ?>">Your dashboard</a>
      <?php else: ?>
        <a class="btn btn--secondary btn--small" href="<?= e(url('/login')) ?>">Sign in</a>
        <a class="btn btn--primary btn--small" href="<?= e(url('/test/new')) ?>">Request a test</a>
      <?php endif; ?>
    </div>
  </div>
</header>
