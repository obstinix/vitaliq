<div class="wrap wrap--narrow section">
  <?php if (!$valid): ?>
    <p class="eyebrow"><span class="num">Account</span> Link unavailable</p>
    <h1>This link can’t be used</h1>
    <?php if (isset($errors['form'])): ?><div class="callout callout--urgent" role="alert"><p><?= e($errors['form']) ?></p></div><?php endif; ?>
    <p class="lede">Reset and invitation links work once and expire. The link may have been used already, replaced by a newer one, or expired.</p>
    <div class="cluster mt-5">
      <a class="btn btn--primary" href="<?= e(url('/forgot-password')) ?>">Request a new link</a>
      <a class="btn btn--secondary" href="<?= e(url('/login')) ?>">Sign in</a>
    </div>
  <?php else: ?>
    <form class="form stack" method="post" action="<?= e(url('/reset-password/' . $token)) ?>" novalidate data-validate data-once>
      <p class="eyebrow"><span class="num">Account</span> <?= $record['purpose'] === 'invite' ? 'Welcome to Vitaliq' : 'Password reset' ?></p>
      <h1><?= e($title) ?></h1>
      <p class="lede">For <strong><?= e($record['email']) ?></strong>. After saving, you will be signed out everywhere and can sign in with the new password.</p>
      <?= $view->partial('error-summary', ['errors' => $errors]) ?>
      <?= csrf_field() ?>
      <input type="text" name="username" value="<?= e($record['email']) ?>" autocomplete="username" hidden>
      <div class="field">
        <label for="f-password">New password</label>
        <input class="input" id="f-password" name="password" type="password" maxlength="128" required autocomplete="new-password" data-rule="password" aria-describedby="hint-password"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
        <p class="hint" id="hint-password">At least 10 characters, with a letter and a digit.</p>
        <?= field_error($errors, 'password') ?>
      </div>
      <div class="field">
        <label for="f-password_confirmation">Confirm new password</label>
        <input class="input" id="f-password_confirmation" name="password_confirmation" type="password" maxlength="128" required autocomplete="new-password" data-match="password"<?= aria_invalid($errors, 'password_confirmation') ?>>
        <?= field_error($errors, 'password_confirmation') ?>
      </div>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit" data-busy-label="Saving…">Save password</button>
      </div>
    </form>
  <?php endif; ?>
</div>
