<?php use Vitaliq\Support\Labels; ?>
<div class="app-head">
  <div>
    <p class="eyebrow">Community</p>
    <h1>Comment moderation</h1>
    <p>Comments are published only after approval. Text is always shown as plain text.</p>
  </div>
</div>
<nav class="tabs" aria-label="Moderation status">
  <?php foreach (Labels::COMMENT_STATUSES as $k => $l): ?>
    <a href="<?= e(url('/admin/comments', ['status' => $k])) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> <span class="count"><?= (int) $counts[$k] ?></span></a>
  <?php endforeach; ?>
</nav>
<?php if ($list['rows'] === []): ?>
  <div class="empty"><h2>Nothing here</h2><p><?= $status === 'pending' ? 'No comments are waiting for review.' : 'No comments have this status.' ?></p></div>
<?php else: ?>
  <ol class="comment-list">
    <?php foreach ($list['rows'] as $c): ?>
      <li>
        <div class="comment-meta">
          <span class="comment-author"><?= e($c['author_name']) ?></span>
          <span class="tag"><?= e(Labels::get(Labels::COMMENT_TOPICS, $c['topic'])) ?></span>
          <?php if ($c['email']): ?><span class="small muted"><?= e($c['email']) ?></span><?php endif; ?>
          <?php if ((int) $c['is_demo'] === 1): ?><span class="tag tag--demo">Demo comment</span><?php endif; ?>
          <time class="small faint" datetime="<?= e(iso_datetime($c['created_at'])) ?>"><?= e(fmt_datetime($c['created_at'])) ?></time>
        </div>
        <p class="comment-body"><?= e($c['body']) ?></p>
        <?php if ($c['moderated_at']): ?>
          <p class="hint mt-4">Moderated by <?= e($c['moderator_name'] ?? '—') ?> on <?= e(fmt_datetime($c['moderated_at'])) ?><?= $c['moderation_note'] ? ': ' . e($c['moderation_note']) : '' ?></p>
        <?php endif; ?>
        <form class="cluster mt-4" method="post" action="<?= e(url('/admin/comments/' . (int) $c['id'])) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="return_status" value="<?= e($status) ?>">
          <label class="visually-hidden" for="note-<?= (int) $c['id'] ?>">Moderation note</label>
          <input class="input" id="note-<?= (int) $c['id'] ?>" name="note" maxlength="300" placeholder="Note (optional)">
          <?php if ($c['status'] !== 'approved'): ?><button class="btn btn--primary btn--small" name="decision" value="approve">Approve</button><?php endif; ?>
          <?php if ($c['status'] !== 'rejected'): ?><button class="btn btn--secondary btn--small" name="decision" value="reject">Reject</button><?php endif; ?>
          <?php if ($c['status'] !== 'spam'): ?><button class="btn btn--ghost btn--small" name="decision" value="spam">Mark spam</button><?php endif; ?>
        </form>
      </li>
    <?php endforeach; ?>
  </ol>
  <?= $view->partial('pagination', ['total' => $list['total'], 'page' => $page, 'perPage' => 20, 'path' => '/admin/comments', 'query' => ['status' => $status]]) ?>
<?php endif; ?>
