<?php use Vitaliq\Support\Labels; ?>
<div class="auth-shell">
  <aside class="auth-aside">
    <p class="eyebrow"><span class="num">Account</span> Why register</p>
    <h2>Request again in seconds.</h2>
    <p>Your details are saved, every request and authorised result stays in your history, and you get a notification each time something changes.</p>
    <p>We ask only for what a testing service needs. No ID numbers, no insurance details.</p>
  </aside>
  <div class="auth-main auth-main--wide">
    <form class="form" method="post" action="<?= e(url('/register')) ?>" novalidate data-validate data-once>
      <h1>Create your account</h1>
      <?= $view->partial('error-summary', ['errors' => $errors]) ?>
      <?= csrf_field() ?>
      <fieldset>
        <legend><span class="num">01</span>About you</legend>
        <div class="form-grid form-grid--2">
          <div class="field span-2">
            <label for="f-full_name">Full name</label>
            <input class="input" id="f-full_name" name="full_name" maxlength="100" required autocomplete="name" data-rule="full_name" value="<?= e(old($old, 'full_name')) ?>"<?= aria_invalid($errors, 'full_name') ?>>
            <?= field_error($errors, 'full_name') ?>
          </div>
          <div class="field">
            <label for="f-date_of_birth">Date of birth</label>
            <input class="input" id="f-date_of_birth" name="date_of_birth" type="date" required autocomplete="bday" data-rule="iso_date" value="<?= e(old($old, 'date_of_birth')) ?>"<?= aria_invalid($errors, 'date_of_birth') ?>>
            <?= field_error($errors, 'date_of_birth') ?>
          </div>
          <div class="field">
            <label for="f-sex">Sex <span class="optional">(optional)</span></label>
            <select class="input" id="f-sex" name="sex">
              <?php foreach (Labels::SEX as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= selected(old($old, 'sex', 'undisclosed'), $key) ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </fieldset>
      <fieldset>
        <legend><span class="num">02</span>Contact and sign-in</legend>
        <div class="form-grid form-grid--2">
          <div class="field">
            <label for="f-email">Email</label>
            <input class="input" id="f-email" name="email" type="email" maxlength="254" required autocomplete="email" data-rule="email" value="<?= e(old($old, 'email')) ?>"<?= aria_invalid($errors, 'email') ?>>
            <?= field_error($errors, 'email') ?>
          </div>
          <div class="field">
            <label for="f-mobile">Mobile number</label>
            <input class="input input--mono" id="f-mobile" name="mobile" type="tel" inputmode="tel" maxlength="16" required autocomplete="tel" data-rule="indian_mobile" value="<?= e(old($old, 'mobile')) ?>"<?= aria_invalid($errors, 'mobile') ?>>
            <?= field_error($errors, 'mobile') ?>
          </div>
          <div class="field">
            <label for="f-password">Password</label>
            <input class="input" id="f-password" name="password" type="password" maxlength="128" required autocomplete="new-password" data-rule="password" aria-describedby="hint-password"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
            <p class="hint" id="hint-password">At least 10 characters, with a letter and a digit. A passphrase works well.</p>
            <?= field_error($errors, 'password') ?>
          </div>
          <div class="field">
            <label for="f-password_confirmation">Confirm password</label>
            <input class="input" id="f-password_confirmation" name="password_confirmation" type="password" maxlength="128" required autocomplete="new-password" data-match="password"<?= aria_invalid($errors, 'password_confirmation') ?>>
            <?= field_error($errors, 'password_confirmation') ?>
          </div>
        </div>
      </fieldset>
      <div class="field">
        <label class="checkbox-line" for="f-consent">
          <input type="checkbox" id="f-consent" name="consent" value="1" required<?= checked(old($old, 'consent') === '1') ?><?= aria_invalid($errors, 'consent') ?>>
          <span>I have read <a href="<?= e(url('/privacy')) ?>">how Vitaliq uses my information</a>.</span>
        </label>
        <?= field_error($errors, 'consent') ?>
      </div>
      <div class="form-actions mt-5">
        <button class="btn btn--primary" type="submit" data-busy-label="Creating account…">Create account</button>
        <span>Already registered? <a href="<?= e(url('/login')) ?>">Sign in</a></span>
      </div>
    </form>
  </div>
</div>
