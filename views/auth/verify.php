<div class="auth-shell">
  <aside class="auth-aside">
    <h2>One more step.</h2>
    <p>Your account uses two-step sign-in. Open your authenticator app and enter the six-digit code for Vitaliq.</p>
  </aside>
  <div class="auth-main">
    <form class="form stack" method="post" action="<?= e(url('/login/verify')) ?>" novalidate data-once>
      <h1>Enter your code</h1>
      <?php if (isset($errors['form'])): ?>
        <div class="callout callout--urgent" role="alert"><p><?= e($errors['form']) ?></p></div>
      <?php endif; ?>
      <?= csrf_field() ?>
      <div class="field">
        <label for="f-code">Six-digit code, or a recovery code</label>
        <input class="input input--mono" id="f-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="13" required autofocus>
        <p class="hint">Lost your phone? Use one of the recovery codes you saved when you turned this on.</p>
      </div>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit" data-busy-label="Checking…">Sign in</button>
        <a href="<?= e(url('/login')) ?>">Start again</a>
      </div>
    </form>
  </div>
</div>
