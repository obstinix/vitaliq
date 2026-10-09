<?php use Vitaliq\Support\Labels; ?>
<header class="page-head">
  <div class="wrap">
    <p class="eyebrow"><span class="num">Voices</span> Community</p>
    <h1>Experiences and feedback.</h1>
    <p class="lede">Share what helped, what confused you, or how Vitaliq could work better. A moderator reviews every comment before it is published.</p>
  </div>
</header>

<div class="wrap section">
  <div class="split split--wide-left">
    <section aria-labelledby="comments-title">
      <h2 id="comments-title" class="visually-hidden">Published comments</h2>
      <nav class="tabs" aria-label="Filter by topic">
        <a href="<?= e(url('/community')) ?>"<?= $topic === '' ? ' aria-current="page"' : '' ?>>All</a>
        <?php foreach (Labels::COMMENT_TOPICS as $key => $label): ?>
          <a href="<?= e(url('/community', ['topic' => $key])) ?>"<?= $topic === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php if ($list['rows'] === []): ?>
        <div class="empty"><h3>No published comments<?= $topic !== '' ? ' on this topic' : '' ?> yet</h3><p>Be the first to share your experience. Comments appear after moderation.</p></div>
      <?php else: ?>
        <ol class="comment-list">
          <?php foreach ($list['rows'] as $c): ?>
            <li>
              <div class="comment-meta">
                <span class="comment-author"><?= e($c['author_name']) ?></span>
                <span class="tag"><?= e(Labels::get(Labels::COMMENT_TOPICS, $c['topic'])) ?></span>
                <?php if ((int) $c['is_demo'] === 1): ?><span class="tag tag--demo">Demo comment</span><?php endif; ?>
                <time class="faint small" datetime="<?= e(iso_datetime($c['created_at'])) ?>"><?= e(fmt_date($c['created_at'])) ?></time>
              </div>
              <p class="comment-body"><?= e($c['body']) ?></p>
            </li>
          <?php endforeach; ?>
        </ol>
        <?= $view->partial('pagination', ['total' => $list['total'], 'page' => $page, 'perPage' => 10, 'path' => '/community', 'query' => ['topic' => $topic]]) ?>
      <?php endif; ?>
    </section>

    <section id="comment-form" aria-labelledby="form-title">
      <h2 id="form-title">Leave a comment</h2>
      <?= $view->partial('error-summary', ['errors' => $errors]) ?>
      <form class="form stack" method="post" action="<?= e(url('/community')) ?>" novalidate data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="stamp" value="<?= e($stamp) ?>">
        <div class="honeypot" aria-hidden="true">
          <label for="f-website">Leave this field empty</label>
          <input id="f-website" type="text" name="website" tabindex="-1" autocomplete="off">
        </div>
        <div class="field">
          <label for="f-author_name">Your name</label>
          <input class="input" id="f-author_name" name="author_name" maxlength="100" required autocomplete="name" data-rule="full_name" value="<?= e(old($old, 'author_name')) ?>"<?= aria_invalid($errors, 'author_name') ?>>
          <?= field_error($errors, 'author_name') ?>
        </div>
        <div class="field">
          <label for="f-email">Email <span class="optional">(optional, never shown)</span></label>
          <input class="input" id="f-email" name="email" type="email" maxlength="254" autocomplete="email" data-rule="email" data-optional value="<?= e(old($old, 'email')) ?>"<?= aria_invalid($errors, 'email') ?>>
          <?= field_error($errors, 'email') ?>
        </div>
        <div class="field">
          <label for="f-topic">Topic</label>
          <select class="input" id="f-topic" name="topic" required<?= aria_invalid($errors, 'topic') ?>>
            <?php foreach (Labels::COMMENT_TOPICS as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= selected(old($old, 'topic', 'general'), $key) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'topic') ?>
        </div>
        <div class="field">
          <label for="f-body">Comment</label>
          <p class="hint" id="hint-body">10–1,000 characters. Do not include personal health details. Links are limited to two.</p>
          <textarea class="input" id="f-body" name="body" minlength="10" maxlength="1000" required aria-describedby="hint-body"<?= aria_invalid($errors, 'body') ?>><?= e(old($old, 'body')) ?></textarea>
          <?= field_error($errors, 'body') ?>
        </div>
        <div class="form-actions">
          <button class="btn btn--primary" type="submit">Submit for review</button>
          <span class="hint">Published after moderation.</span>
        </div>
      </form>
    </section>
  </div>
</div>
