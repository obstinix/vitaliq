<?php use Vitaliq\Support\Labels; ?>
<div class="app-head">
  <div>
    <p class="eyebrow">Account</p>
    <h1>Your profile</h1>
    <p>Signed in as <strong><?= e($currentUser['email']) ?></strong>. Your email is your sign-in name and cannot be changed here.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<form class="form" method="post" action="<?= e(url('/account')) ?>" novalidate data-validate data-once>
  <?= csrf_field() ?>
  <fieldset>
    <legend><span class="num">01</span>Personal details</legend>
    <div class="form-grid form-grid--2">
      <div class="field span-2">
        <label for="f-full_name">Full name</label>
        <input class="input" id="f-full_name" name="full_name" maxlength="100" required autocomplete="name" data-rule="full_name" value="<?= e(old($old, 'full_name')) ?>"<?= aria_invalid($errors, 'full_name') ?>>
        <?= field_error($errors, 'full_name') ?>
      </div>
      <div class="field">
        <label for="f-mobile">Mobile number<?= $patient === null ? ' <span class="optional">(optional)</span>' : '' ?></label>
        <input class="input input--mono" id="f-mobile" name="mobile" type="tel" inputmode="tel" maxlength="16"<?= $patient !== null ? ' required' : ' data-optional' ?> autocomplete="tel" data-rule="indian_mobile" value="<?= e(old($old, 'mobile')) ?>"<?= aria_invalid($errors, 'mobile') ?>>
        <?= field_error($errors, 'mobile') ?>
      </div>
      <?php if ($patient !== null): ?>
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
      <?php endif; ?>
    </div>
  </fieldset>
  <?php if ($patient !== null): ?>
    <fieldset>
      <legend><span class="num">02</span>Address <span class="optional">(used to pre-fill home collection)</span></legend>
      <div class="form-grid form-grid--3">
        <div class="field span-all">
          <label for="f-address_line">Address</label>
          <input class="input" id="f-address_line" name="address_line" maxlength="200" autocomplete="street-address" value="<?= e(old($old, 'address_line')) ?>"<?= aria_invalid($errors, 'address_line') ?>>
          <?= field_error($errors, 'address_line') ?>
        </div>
        <div class="field">
          <label for="f-city">City</label>
          <input class="input" id="f-city" name="city" maxlength="80" autocomplete="address-level2" value="<?= e(old($old, 'city')) ?>"<?= aria_invalid($errors, 'city') ?>>
          <?= field_error($errors, 'city') ?>
        </div>
        <div class="field">
          <label for="f-state_code">State or union territory</label>
          <select class="input" id="f-state_code" name="state_code"<?= aria_invalid($errors, 'state_code') ?>>
            <option value="">Not set</option>
            <?php foreach ($states as $code => $name): ?>
              <option value="<?= e($code) ?>"<?= selected(old($old, 'state_code'), (string) $code) ?>><?= e($name) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'state_code') ?>
        </div>
        <div class="field">
          <label for="f-pincode">PIN code</label>
          <input class="input input--mono" id="f-pincode" name="pincode" inputmode="numeric" maxlength="7" autocomplete="postal-code" data-rule="pincode" data-optional value="<?= e(old($old, 'pincode')) ?>"<?= aria_invalid($errors, 'pincode') ?>>
          <?= field_error($errors, 'pincode') ?>
        </div>
      </div>
    </fieldset>
  <?php endif; ?>
  <div class="form-actions">
    <button class="btn btn--primary" type="submit" data-busy-label="Saving…">Save changes</button>
    <a href="<?= e(url('/account/password')) ?>">Change password</a>
  </div>
</form>
