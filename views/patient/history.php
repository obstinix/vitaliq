<div class="app-head">
  <div>
    <p class="eyebrow">Your care</p>
    <h1>Testing history</h1>
    <p>Every request on your account, newest first.</p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/patient/requests/new')) ?>">Request a test</a>
</div>
<?php if ($requests === []): ?>
  <div class="empty">
    <h2>No requests yet</h2>
    <p>Your requests, their status and authorised results will be listed here.</p>
    <a class="btn btn--secondary" href="<?= e(url('/patient/requests/new')) ?>">Request your first test</a>
  </div>
<?php else: ?>
  <?= $view->partial('requests-table', ['rows' => $requests]) ?>
<?php endif; ?>
