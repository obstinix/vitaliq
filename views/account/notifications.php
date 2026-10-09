<div class="app-head">
  <div>
    <p class="eyebrow">Account</p>
    <h1>Notifications</h1>
    <p>Updates about your requests and work. The bell checks for new ones every <?= (int) $pollSeconds ?> seconds while this tab is open.</p>
  </div>
  <form method="post" action="<?= e(url('/notifications/read-all')) ?>">
    <?= csrf_field() ?>
    <button class="btn btn--secondary" type="submit">Mark all as read</button>
  </form>
</div>
<nav class="tabs" aria-label="Filter notifications">
  <a href="<?= e(url('/notifications')) ?>"<?= !$unreadOnly ? ' aria-current="page"' : '' ?>>All</a>
  <a href="<?= e(url('/notifications', ['filter' => 'unread'])) ?>"<?= $unreadOnly ? ' aria-current="page"' : '' ?>>Unread <span class="count"><?= (int) $unreadCount ?></span></a>
</nav>
<?php if ($list['rows'] === []): ?>
  <div class="empty"><h2><?= $unreadOnly ? 'You are all caught up' : 'No notifications yet' ?></h2><p>New updates will appear here and on the bell.</p></div>
<?php else: ?>
  <div class="panel panel--flush">
    <ul class="note-list">
      <?php foreach ($list['rows'] as $n): ?>
        <li class="<?= $n['read_at'] === null ? 'unread' : '' ?>">
          <a href="<?= e(url('/notifications/' . (int) $n['id'])) ?>">
            <span class="note-title"><?= e($n['title']) ?><?= $n['read_at'] === null ? '<span class="visually-hidden"> (unread)</span>' : '' ?></span>
            <span class="note-body"><?= e($n['body']) ?></span>
            <time class="note-time" datetime="<?= e(iso_datetime($n['created_at'])) ?>"><?= e(fmt_datetime($n['created_at'])) ?></time>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?= $view->partial('pagination', ['total' => $list['total'], 'page' => $page, 'perPage' => 20, 'path' => '/notifications', 'query' => ['filter' => $unreadOnly ? 'unread' : '']]) ?>
<?php endif; ?>
