<?php
use Vitaliq\Support\Labels;

$user = $currentUser;
$path = $currentPath ?? '/';
$navs = [
    'patient' => [
        'Your care' => [
            '/patient' => 'Dashboard',
            '/patient/requests/new' => 'Request a test',
            '/patient/requests' => 'Testing history',
            '/patient/link' => 'Link a request',
        ],
    ],
    'phlebotomist' => [
        'Field work' => ['/staff' => 'Your collections'],
    ],
    'admin' => [
        'Operations' => [
            '/admin' => 'Dashboard',
            '/admin/requests' => 'Requests',
            '/admin/reports' => 'Reports',
            '/admin/phlebotomists' => 'Phlebotomists',
        ],
        'Community' => [
            '/admin/comments' => 'Comments',
            '/admin/announcements' => 'Announcements',
        ],
        'Governance' => [
            '/admin/users' => 'Users',
            '/admin/audit' => 'Audit log',
            '/qa' => 'STQA lab',
        ],
    ],
    'qa' => [
        'STQA lab' => [
            '/qa' => 'Overview',
            '/qa/validator' => 'Rule tester',
            '/qa/cases' => 'Test cases',
            '/qa/runs' => 'Executions',
            '/qa/defects' => 'Defects',
        ],
    ],
];
$sections = $navs[$user['role']] ?? [];
if ($user['role'] === 'admin' && str_starts_with($path, '/qa')) {
    $sections = $navs['qa'] + ['Operations' => ['/admin' => 'Back to operations']];
}
$sections['Account'] = ['/account' => 'Profile', '/account/password' => 'Password', '/notifications' => 'Notifications'];

/* Longest matching prefix wins, so /patient/requests/12 highlights "Testing history". */
$active = '';
foreach ($sections as $links) {
    foreach (array_keys($links) as $href) {
        if (($path === $href || str_starts_with($path, $href . '/')) && strlen($href) > strlen($active)) {
            $active = $href;
        }
    }
}
?>
<!doctype html>
<html lang="en-IN">
<head>
<?= $view->partial('head', ['title' => $title ?? 'Vitaliq']) ?>
</head>
<body class="layout-app" data-poll="<?= (int) ($pollSeconds ?? 30) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="app-bar">
  <div class="wrap app-bar__inner">
    <?= $view->partial('wordmark', ['href' => '/']) ?>
    <span class="role-pill"><?= e(Labels::get(Labels::ROLES, $user['role'])) ?></span>
    <div class="app-bar__actions">
      <div class="menu-wrap" data-bell>
        <button class="icon-button" type="button" aria-expanded="false" aria-controls="bell-panel" data-bell-toggle>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
          <span class="visually-hidden" data-bell-label>Notifications, <?= (int) $unreadCount ?> unread</span>
          <span class="badge-count" data-bell-count<?= $unreadCount > 0 ? '' : ' hidden' ?> aria-hidden="true"><?= (int) $unreadCount ?></span>
        </button>
        <div class="popover" id="bell-panel" hidden>
          <div class="popover__head">
            <h2>Notifications</h2>
            <form method="post" action="<?= e(url('/notifications/read-all')) ?>" data-mark-all>
              <?= csrf_field() ?>
              <button class="link-button small" type="submit">Mark all read</button>
            </form>
          </div>
          <ul class="note-list" data-bell-list>
            <li><a href="<?= e(url('/notifications')) ?>"><span class="note-body">Open notifications</span></a></li>
          </ul>
          <div class="popover__foot"><a href="<?= e(url('/notifications')) ?>">View all notifications</a></div>
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
            <li><a href="<?= e(url('/account')) ?>">Profile</a></li>
            <li><a href="<?= e(url('/account/password')) ?>">Change password</a></li>
            <li><a href="<?= e(url('/')) ?>">Public site</a></li>
            <li>
              <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit">Sign out</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</header>
<div class="app-shell">
  <aside class="side-nav">
    <nav aria-label="Section">
      <?php foreach ($sections as $heading => $links): ?>
        <h2><?= e($heading) ?></h2>
        <ul>
          <?php foreach ($links as $href => $label): ?>
            <li><a href="<?= e(url($href)) ?>"<?= $href === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </nav>
  </aside>
  <main id="main" class="app-main" tabindex="-1">
    <?= $view->partial('flash', ['flash' => $flash ?? []]) ?>
    <?= $content ?>
  </main>
</div>
<div class="visually-hidden" aria-live="polite" data-live></div>
<?= $view->partial('scripts', ['scripts' => $scripts ?? [], 'motion' => false, 'clientRules' => $clientRules ?? []]) ?>
</body>
</html>
