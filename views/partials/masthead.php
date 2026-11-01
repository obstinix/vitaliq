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
$home = $user === null ? null : app()->auth()->homePath();
$locale = \Vitaliq\Support\Lang::locale();
?>
<header class="masthead">
  <div class="wrap masthead__inner">
    <?= $view->partial('wordmark', ['tagline' => true]) ?>
    <button class="btn btn--secondary btn--small nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" data-nav-toggle>
      Menu
    </button>
    <nav class="primary-nav" id="primary-nav" aria-label="Main"<?= $locale !== 'en' ? ' lang="' . e($locale) . '"' : '' ?>>
      <ul>
        <?php foreach ($links as $href => $label): ?>
          <li><a href="<?= e(url($href)) ?>"<?= $path === $href ? ' aria-current="page"' : '' ?>><?= e(t($label)) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="masthead__actions">
      <form class="lang-switch" method="post" action="<?= e(url('/locale')) ?>">
        <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($path) ?>">
        <label class="visually-hidden" for="lang-select"><?= e(t('Language')) ?></label>
        <select id="lang-select" name="locale" data-autosubmit>
          <?php foreach (\Vitaliq\Support\Lang::LOCALES as $code => $name): ?><option value="<?= e($code) ?>" lang="<?= e($code) ?>"<?= selected($locale, $code) ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn--secondary btn--small" type="submit">OK</button></noscript>
      </form>
      <?php if ($user !== null): ?>
        <a class="btn btn--primary btn--small" href="<?= e(url($home)) ?>"><?= e(t('Your dashboard')) ?></a>
      <?php else: ?>
        <a class="btn btn--secondary btn--small" href="<?= e(url('/login')) ?>"><?= e(t('Sign in')) ?></a>
        <a class="btn btn--primary btn--small" href="<?= e(url('/test/new')) ?>"><?= e(t('Request a test')) ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>
