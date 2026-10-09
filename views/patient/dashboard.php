<?php
$first = explode(' ', (string) $currentUser['full_name'])[0];
?>
<div class="app-head">
  <div>
    <p class="eyebrow">Patient dashboard</p>
    <h1>Hello, <?= e($first) ?>.</h1>
    <p><?= count($active) === 0 ? 'You have no requests in progress.' : (count($active) === 1 ? 'You have one request in progress.' : count($active) . ' requests are in progress.') ?></p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/patient/requests/new')) ?>">Request a test</a>
</div>

<div class="grid-2">
  <div class="stack">
    <section class="panel" aria-labelledby="current-title">
      <div class="panel__head"><h2 id="current-title">Current requests</h2></div>
      <?php if ($active === []): ?>
        <div class="empty">
          <h3>Nothing in progress</h3>
          <p>When you request a test it appears here with each step as it happens.</p>
          <a class="btn btn--secondary" href="<?= e(url('/patient/requests/new')) ?>">Request a test</a>
        </div>
      <?php else: ?>
        <?= $view->partial('requests-table', ['rows' => $active]) ?>
      <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="past-title">
      <div class="panel__head">
        <h2 id="past-title">Recent results and history</h2>
        <a href="<?= e(url('/patient/requests')) ?>">Full history</a>
      </div>
      <?php if ($past === []): ?>
        <p class="muted mb-0">Completed, cancelled and rejected requests will be listed here.</p>
      <?php else: ?>
        <?= $view->partial('requests-table', ['rows' => $past]) ?>
      <?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <section class="panel" aria-labelledby="notes-title">
      <div class="panel__head"><h2 id="notes-title">Notifications</h2><a href="<?= e(url('/notifications')) ?>">All</a></div>
      <?php if ($notifications === []): ?>
        <p class="muted mb-0">No notifications yet.</p>
      <?php else: ?>
        <ul class="note-list">
          <?php foreach ($notifications as $n): ?>
            <li class="<?= $n['read_at'] === null ? 'unread' : '' ?>">
              <a href="<?= e(url('/notifications/' . (int) $n['id'])) ?>">
                <span class="note-title"><?= e($n['title']) ?><?= $n['read_at'] === null ? '<span class="visually-hidden"> (unread)</span>' : '' ?></span>
                <span class="note-body"><?= e($n['body']) ?></span>
                <time class="note-time" datetime="<?= e(iso_datetime($n['created_at'])) ?>"><?= e(fmt_datetime($n['created_at'])) ?></time>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="profile-title">
      <div class="panel__head"><h2 id="profile-title">Your details</h2><a href="<?= e(url('/account')) ?>">Edit</a></div>
      <?php if ($patient !== null): ?>
        <dl class="details">
          <dt>Name</dt><dd><?= e($patient['full_name']) ?></dd>
          <dt>Date of birth</dt><dd><?= e(fmt_date($patient['date_of_birth'])) ?></dd>
          <dt>Mobile</dt><dd class="mono"><?= e(format_mobile($patient['mobile'])) ?></dd>
          <dt>Email</dt><dd><?= e($patient['account_email']) ?></dd>
          <dt>Address</dt><dd><?= $patient['city'] ? e(trim(($patient['address_line'] ? $patient['address_line'] . ', ' : '') . $patient['city'] . ', ' . ($patient['state_name'] ?? ''))) : '<span class="faint">Not added yet</span>' ?></dd>
        </dl>
      <?php endif; ?>
    </section>

    <div class="callout callout--note">
      <h3>Requested a test before you registered?</h3>
      <p><a href="<?= e(url('/patient/link')) ?>">Link it to your account</a> to see it here.</p>
    </div>
  </div>
</div>
