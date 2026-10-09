<?php use Vitaliq\Support\Labels; ?>
<header class="page-head">
  <div class="wrap">
    <p class="eyebrow"><span class="num">New patient</span> Request a COVID-19 test</p>
    <h1>Request a test in about three minutes.</h1>
    <p class="lede">No account needed. You will receive a reference to track the request. <a href="<?= e(url('/login')) ?>">Sign in</a> instead if you have used Vitaliq before.</p>
  </div>
</header>

<div class="wrap section">
  <div class="split split--wide-left">
    <div>
      <?= $view->partial('error-summary', ['errors' => $errors]) ?>
      <form class="form" method="post" action="<?= e(url('/test/new')) ?>" novalidate data-validate data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="form_token" value="<?= e($formToken) ?>">

        <fieldset>
          <legend><span class="num">01</span>The person being tested</legend>
          <div class="form-grid form-grid--2">
            <div class="field span-2">
              <label for="f-full_name">Full name</label>
              <input class="input" id="f-full_name" name="full_name" maxlength="100" required autocomplete="name" data-rule="full_name" value="<?= e(old($old, 'full_name')) ?>"<?= aria_invalid($errors, 'full_name') ?>>
              <?= field_error($errors, 'full_name') ?>
            </div>
            <div class="field">
              <label for="f-date_of_birth">Date of birth</label>
              <input class="input" id="f-date_of_birth" name="date_of_birth" type="date" required max="<?= e($today) ?>" autocomplete="bday" data-rule="iso_date" value="<?= e(old($old, 'date_of_birth')) ?>"<?= aria_invalid($errors, 'date_of_birth') ?>>
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
            <div class="field">
              <label for="f-mobile">Mobile number</label>
              <input class="input input--mono" id="f-mobile" name="mobile" type="tel" inputmode="tel" maxlength="16" required autocomplete="tel" data-rule="indian_mobile" value="<?= e(old($old, 'mobile')) ?>" aria-describedby="hint-mobile"<?= isset($errors['mobile']) ? ' aria-invalid="true"' : '' ?>>
              <p class="hint" id="hint-mobile">Indian mobile, e.g. 98765 43210. You will need it to track the test.</p>
              <?= field_error($errors, 'mobile') ?>
            </div>
            <div class="field">
              <label for="f-email">Email <span class="optional">(optional)</span></label>
              <input class="input" id="f-email" name="email" type="email" maxlength="254" autocomplete="email" data-rule="email" data-optional value="<?= e(old($old, 'email')) ?>"<?= aria_invalid($errors, 'email') ?>>
              <?= field_error($errors, 'email') ?>
            </div>
          </div>
        </fieldset>

        <?= $view->partial('test-fields', compact('old', 'errors', 'states', 'today', 'maxDate') + ['n' => 2]) ?>

        <div class="form-actions">
          <button class="btn btn--primary" type="submit" data-busy-label="Submitting…">Submit request</button>
          <span class="hint">You will get a reference on the next page.</span>
        </div>
      </form>
    </div>
    <aside class="stack">
      <div class="callout">
        <h2>What happens next</h2>
        <ol class="mb-0">
          <li>You get a reference such as <span class="ref">VQ-261010-7K3MP</span>.</li>
          <li>An administrator assigns a phlebotomist.</li>
          <li>Track each step with your reference, mobile number and date of birth.</li>
        </ol>
      </div>
      <div class="callout callout--urgent">
        <h2>Feeling very unwell?</h2>
        <p>Do not wait for a test. For severe breathing difficulty or chest pain, call <strong>112</strong>.</p>
      </div>
      <div class="callout callout--note">
        <p><strong>Demonstration system.</strong> No real sample will be collected. Please use made-up details.</p>
      </div>
    </aside>
  </div>
</div>
