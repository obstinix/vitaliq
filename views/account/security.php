<div class="app-head">
  <div>
    <h1><?= e(t('Security')) ?></h1>
    <p>Two-step sign-in is <?= $enabled ? 'on' : 'off' ?> · <?= count($sessions) ?> signed-in <?= count($sessions) === 1 ? 'session' : 'sessions' ?>. Security notices also arrive in your notifications.</p>
  </div>
  <a class="btn btn--secondary" href="<?= e(url('/account/password')) ?>"><?= e(t('Change password')) ?></a>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>

<?php if ($recovery !== null): ?>
  <section class="panel" aria-labelledby="rc">
    <div class="panel__head"><h2 id="rc">Save your recovery codes</h2><span class="meta">Shown once</span></div>
    <p>Each code signs you in once if you lose your phone. Keep them somewhere safe, away from your phone.</p>
    <ul class="recovery-codes"><?php foreach ($recovery as $c): ?><li class="ref"><?= e($c) ?></li><?php endforeach; ?></ul>
    <button class="btn btn--secondary" type="button" data-print>Print</button>
  </section>
<?php endif; ?>

<div class="vq-grid">
  <section class="panel span-6" aria-labelledby="two-step" id="two-step">
    <div class="panel__head"><h2>Two-step sign-in</h2><span class="status status--<?= $enabled ? 'ok' : 'cancelled' ?>"><?= $enabled ? 'On' : 'Off' ?></span></div>
    <?php if ($enabled): ?>
      <p>Signing in needs your password and a code from your authenticator app. <?= (int) $codesLeft ?> recovery <?= $codesLeft === 1 ? 'code' : 'codes' ?> left.</p>
      <details class="confirm confirm--danger"><summary>Turn off two-step sign-in</summary><div class="confirm__body">
        <form method="post" action="<?= e(url('/account/security/two-step/disable')) ?>"><?= csrf_field() ?>
          <div class="field"><label for="f-password">Your password</label><input class="input" type="password" id="f-password" name="password" autocomplete="current-password"<?= aria_invalid($errors, 'password') ?>><?= field_error($errors, 'password') ?></div>
          <button class="btn btn--danger mt-4" type="submit">Turn off</button>
        </form>
      </div></details>
    <?php elseif ($pending !== null): ?>
      <ol>
        <li>Open an authenticator app (Google Authenticator, Microsoft Authenticator, Aegis or similar).</li>
        <li>Scan this code, or type the key below.</li>
        <li>Enter the six-digit code the app shows.</li>
      </ol>
      <div class="qr-box"><?= $qr ?></div>
      <p class="hint mt-4">Key: <span class="ref"><?= e(implode(' ', str_split($pending, 4))) ?></span></p>
      <form method="post" action="<?= e(url('/account/security/two-step/confirm')) ?>" class="mt-4" novalidate><?= csrf_field() ?>
        <div class="field"><label for="f-code">Code from the app</label><input class="input input--mono" id="f-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"<?= aria_invalid($errors, 'code') ?>><?= field_error($errors, 'code') ?></div>
        <div class="form-actions"><button class="btn btn--primary" type="submit">Turn on</button></div>
      </form>
    <?php else: ?>
      <p>Protect your health records with a code from your phone as well as your password.</p>
      <form method="post" action="<?= e(url('/account/security/two-step')) ?>"><?= csrf_field() ?><button class="btn btn--primary" type="submit">Set up two-step sign-in</button></form>
    <?php endif; ?>
  </section>

  <section class="panel span-6" aria-labelledby="sess">
    <div class="panel__head"><h2 id="sess">Where you are signed in</h2>
      <?php if (count($sessions) > 1): ?><form method="post" action="<?= e(url('/account/security/sessions/others')) ?>"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit">Sign out everywhere else</button></form><?php endif; ?></div>
    <?php if ($sessions === []): ?>
      <p class="empty">No sessions recorded.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($sessions as $s): ?>
          <li><span class="grow"><?= e($s['device']) ?><?= $s['current'] ? ' <span class="status status--active">This device</span>' : '' ?><span class="sub">Signed in <?= e(fmt_datetime($s['created_at'])) ?> · last active <?= e(fmt_datetime($s['last_seen_at'])) ?></span></span>
            <?php if (!$s['current']): ?><form method="post" action="<?= e(url('/account/security/sessions/' . (int) $s['id'])) ?>"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit">Sign out</button></form><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php if (($currentUser['role'] ?? '') !== 'admin'): ?>
  <section class="panel span-12" aria-labelledby="del">
    <div class="panel__head"><h2 id="del">Delete your account</h2></div>
    <?php if ($deletion !== null && $deletion['status'] === 'requested'): ?>
      <p>You asked for deletion on <?= e(fmt_date($deletion['created_at'])) ?>. An administrator will complete it.</p>
    <?php else: ?>
      <?php if ($deletion !== null && $deletion['status'] === 'declined'): ?><p class="callout callout--note">Your earlier request was declined: <?= e((string) $deletion['decision_note']) ?></p><?php endif; ?>
      <p>Deleting removes your documents, health passport, emergency contacts, family links, preferences and sign-in. Test results already issued are kept as clinical records, no longer linked to any login.</p>
      <details class="confirm confirm--danger"><summary>Ask for deletion</summary><div class="confirm__body">
        <form method="post" action="<?= e(url('/account/security/delete')) ?>"><?= csrf_field() ?>
          <div class="field"><label for="f-reason">Reason <span class="optional">optional</span></label><input class="input" id="f-reason" name="reason" maxlength="500"></div>
          <label class="checkbox-line mt-4" id="f-confirm"><input type="checkbox" name="confirm" value="1"> <span>I understand this cannot be undone.</span></label><?= field_error($errors, 'confirm') ?>
          <button class="btn btn--danger mt-4" type="submit">Ask for deletion</button>
        </form>
      </div></details>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
