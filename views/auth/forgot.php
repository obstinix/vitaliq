<div class="wrap wrap--narrow section">
  <form class="form stack" method="post" action="<?= e(url('/forgot-password')) ?>" novalidate data-once>
    <p class="eyebrow"><span class="num">Account</span> Password reset</p>
    <h1>Reset your password</h1>
    <p class="lede">Enter the email you registered with. If an account exists, a single-use reset link valid for 30 minutes is prepared for it.</p>
    <?= csrf_field() ?>
    <div class="field">
      <label for="f-email">Email</label>
      <input class="input" id="f-email" name="email" type="email" maxlength="254" required autocomplete="email" value="<?= e(old($old, 'email')) ?>"<?= aria_invalid($errors, 'email') ?>>
      <?= field_error($errors, 'email') ?>
    </div>
    <div class="form-actions">
      <button class="btn btn--primary" type="submit">Prepare reset link</button>
      <a href="<?= e(url('/login')) ?>">Back to sign in</a>
    </div>
    <div class="callout callout--note">
      <p><strong>Email delivery is not configured in this deployment.</strong> No message reaches a real inbox. On a local development install the message is written to <code>storage/mail/</code>, where the developer can open it.</p>
    </div>
  </form>
</div>
