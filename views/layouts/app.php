<?php
use Vitaliq\Support\Labels;
use Vitaliq\Support\Navigation;

$user = $currentUser;
$path = $currentPath ?? '/';
$sections = Navigation::for($user['role'], app()->router, static fn (string $p): bool => app()->can($p), $path);
$active = Navigation::active($sections, $path);
$words = preg_split('/\s+/', trim((string) $user['full_name'])) ?: [];
$initials = implode('', array_map(static fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2)));
$getRoutes = array_column(array_filter(app()->router->list(), static fn ($r) => $r['method'] === 'GET'), 'pattern');
$hasSearch = in_array('/search', $getRoutes, true);
$locale = \Vitaliq\Support\Lang::locale();
$a11y = (int) ($user['accessibility_mode'] ?? 0) === 1;
?>
<!doctype html>
<html lang="en-IN">
<head>
<?= $view->partial('head', ['title' => $title ?? 'Vitaliq', 'appCss' => true]) ?>
</head>
<body class="layout-app<?= $a11y ? ' a11y-mode' : '' ?>" data-poll="<?= (int) ($pollSeconds ?? 30) ?>">
<a class="skip-link" href="#main"><?= e(t('Skip to content')) ?></a>
<div class="vq-app">
  <aside class="vq-side" id="vq-side" aria-label="Main navigation"<?= $locale !== 'en' ? ' lang="' . e($locale) . '"' : '' ?>>
    <div class="vq-side__head">
      <a class="vq-brand" href="<?= e(url(app()->auth()->homePath())) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="1" width="22" height="22" rx="6" fill="#1b6f73"/><path d="M6.5 7.5 12 17l5.5-9.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="17.5" cy="7.5" r="1.6" fill="#9fd6c4"/></svg>
        <b>Vitaliq</b>
      </a>
      <button class="icon-button vq-close" type="button" data-nav-close aria-label="<?= e(t('Close menu')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <p class="vq-scope"><strong><?= e(t(Labels::get(Labels::ROLES, $user['role']))) ?></strong><?= e($scopeLabel ?? t('Vitaliq health network')) ?></p>
    <nav class="vq-nav" aria-label="Section">
      <?php foreach ($sections as $heading => $links): ?>
        <h2><?= e(t($heading)) ?></h2>
        <ul>
          <?php foreach ($links as $link): ?>
            <li><a href="<?= e(url($link['href'])) ?>"<?= $link['href'] === $active ? ' aria-current="page"' : '' ?>><?= Navigation::icon($link['icon']) ?><span><?= e(t($link['label'])) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </nav>
    <div class="vq-user">
      <span class="vq-avatar" aria-hidden="true"><?= e($initials) ?></span>
      <span><?= e($user['full_name']) ?><small><?= e($user['email']) ?></small></span>
    </div>
  </aside>

  <header class="vq-top"<?= $locale !== 'en' ? ' lang="' . e($locale) . '"' : '' ?>>
    <button class="icon-button vq-menu-btn" type="button" aria-expanded="false" aria-controls="vq-side" data-nav-toggle>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      <span class="visually-hidden"><?= e(t('Menu')) ?></span>
    </button>
    <?php if ($hasSearch): ?>
      <form class="vq-search" method="get" action="<?= e(url('/search')) ?>" role="search">
        <?= Navigation::icon('search') ?>
        <label class="visually-hidden" for="vq-q"><?= e(t('Search')) ?></label>
        <input id="vq-q" name="q" type="search" maxlength="80" placeholder="<?= e(t('Search patients, references, samples')) ?>" value="<?= e($searchQuery ?? '') ?>">
      </form>
    <?php endif; ?>
    <div class="vq-top__actions">
      <div class="menu-wrap" data-bell>
        <button class="icon-button" type="button" aria-expanded="false" aria-controls="bell-panel" data-bell-toggle>
          <?= Navigation::icon('bell') ?>
          <span class="visually-hidden" data-bell-label>Notifications, <?= (int) $unreadCount ?> unread</span>
          <span class="badge-count" data-bell-count<?= $unreadCount > 0 ? '' : ' hidden' ?> aria-hidden="true"><?= (int) $unreadCount ?></span>
        </button>
        <div class="popover" id="bell-panel" hidden>
          <div class="popover__head">
            <h2><?= e(t('Notifications')) ?></h2>
            <form method="post" action="<?= e(url('/notifications/read-all')) ?>" data-mark-all>
              <?= csrf_field() ?>
              <button class="link-button small" type="submit"><?= e(t('Mark all read')) ?></button>
            </form>
          </div>
          <ul class="note-list" data-bell-list>
            <li><a href="<?= e(url('/notifications')) ?>"><span class="note-body"><?= e(t('Open notifications')) ?></span></a></li>
          </ul>
          <div class="popover__foot"><a href="<?= e(url('/notifications')) ?>"><?= e(t('View all notifications')) ?></a></div>
        </div>
      </div>
      <div class="menu-wrap" data-menu>
        <button class="icon-button" type="button" aria-expanded="false" aria-controls="user-menu" data-menu-toggle>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
          <span class="visually-hidden">Account menu for <?= e($user['full_name']) ?></span>
        </button>
        <div class="popover user-menu" id="user-menu" hidden>
          <div class="popover__head"><h2><?= e($user['full_name']) ?></h2></div>
          <ul>
            <li><a href="<?= e(url('/account')) ?>"><?= e(t('Profile')) ?></a></li>
            <li><a href="<?= e(url('/account/password')) ?>"><?= e(t('Change password')) ?></a></li>
            <li><a href="<?= e(url('/')) ?>"><?= e(t('Public site')) ?></a></li>
            <li>
              <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit"><?= e(t('Sign out')) ?></button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </header>

  <main id="main" class="vq-sheet" tabindex="-1">
    <?= $view->partial('flash', ['flash' => $flash ?? []]) ?>
    <?= $content ?>
  </main>
</div>
<div class="visually-hidden" aria-live="polite" data-live></div>
<?= $view->partial('scripts', ['scripts' => $scripts ?? [], 'motion' => false, 'clientRules' => $clientRules ?? []]) ?>
</body>
</html>
