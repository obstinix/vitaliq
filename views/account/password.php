<div class="app-head">
  <div>
    <p class="eyebrow">Account</p>
    <h1>Change password</h1>
    <p>Other devices signed in to your account will be signed out.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<form class="form stack" method="post" action="<?= e(url('/account/password')) ?>" novalidate data-validate data-once>
  <?= csrf_field() ?>
  <input type="text" name="username" value="<?= e($currentUser['email']) ?>" autocomplete="username" hidden>
  <div class="field">
    <label for="f-current_password">Current password</label>
    <input class="input" id="f-current_password" name="current_password" type="password" maxlength="128" required autocomplete="current-password"<?= aria_invalid($errors, 'current_password') ?>>
    <?= field_error($errors, 'current_password') ?>
  </div>
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
    <button class="btn btn--primary" type="submit" data-busy-label="Saving…">Change password</button>
  </div>
</form>
