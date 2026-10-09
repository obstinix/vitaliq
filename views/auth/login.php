<div class="auth-shell">
  <aside class="auth-aside">
    <p class="eyebrow"><span class="num">Vitaliq</span> Welcome back</p>
    <h2>Your tests, in one place.</h2>
    <p>Patients see every request and result. Staff see the work assigned to them. Everyone sees exactly what they are allowed to, and no more.</p>
  </aside>
  <div class="auth-main">
    <form class="form stack" method="post" action="<?= e(url('/login')) ?>" novalidate data-once>
      <h1>Sign in</h1>
      <?php if (isset($errors['form'])): ?>
        <div class="callout callout--urgent" role="alert"><p><?= e($errors['form']) ?></p></div>
      <?php endif; ?>
      <?= csrf_field() ?>
      <div class="field">
        <label for="f-email">Email</label>
        <input class="input" id="f-email" name="email" type="email" maxlength="254" required autocomplete="username" autofocus value="<?= e(old($old, 'email')) ?>">
      </div>
      <div class="field">
        <label for="f-password">Password</label>
        <input class="input" id="f-password" name="password" type="password" maxlength="128" required autocomplete="current-password">
      </div>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit" data-busy-label="Signing in…">Sign in</button>
        <a href="<?= e(url('/forgot-password')) ?>">Forgotten your password?</a>
      </div>
      <p class="mt-5">New to Vitaliq? <a href="<?= e(url('/register')) ?>">Create an account</a> or <a href="<?= e(url('/test/new')) ?>">request a test without one</a>.</p>
    </form>
  </div>
</div>
