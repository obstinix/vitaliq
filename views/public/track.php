<header class="page-head">
  <div class="wrap">
    <p class="eyebrow"><span class="num">Track</span> Follow a request</p>
    <h1>Where is my test?</h1>
    <p class="lede">Enter the three details exactly as you gave them. All three must match, so nobody can look up a result with a reference alone.</p>
  </div>
</header>
<div class="wrap section">
  <div class="split">
    <div>
      <?= $view->partial('error-summary', ['errors' => $errors]) ?>
      <form class="form stack" method="post" action="<?= e(url('/track')) ?>" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="f-reference">Reference</label>
          <input class="input input--mono" id="f-reference" name="reference" maxlength="15" required autocomplete="off" spellcheck="false" placeholder="VQ-261010-7K3MP" data-rule="reference" value="<?= e(old($old, 'reference')) ?>"<?= aria_invalid($errors, 'reference') ?>>
          <?= field_error($errors, 'reference') ?>
        </div>
        <div class="field">
          <label for="f-mobile">Mobile number</label>
          <input class="input input--mono" id="f-mobile" name="mobile" type="tel" inputmode="tel" maxlength="16" required autocomplete="tel" data-rule="indian_mobile" value="<?= e(old($old, 'mobile')) ?>"<?= aria_invalid($errors, 'mobile') ?>>
          <?= field_error($errors, 'mobile') ?>
        </div>
        <div class="field">
          <label for="f-date_of_birth">Date of birth</label>
          <input class="input" id="f-date_of_birth" name="date_of_birth" type="date" required autocomplete="bday" data-rule="iso_date" value="<?= e(old($old, 'date_of_birth')) ?>"<?= aria_invalid($errors, 'date_of_birth') ?>>
          <?= field_error($errors, 'date_of_birth') ?>
        </div>
        <div class="form-actions">
          <button class="btn btn--primary" type="submit">Find my request</button>
        </div>
      </form>
    </div>
    <aside class="stack">
      <div class="callout callout--note">
        <h2>Have an account?</h2>
        <p><a href="<?= e(url('/login')) ?>">Sign in</a> to see all of your requests and notifications without entering these details.</p>
      </div>
      <p class="hint">Lookups are limited to <?= (int) \Vitaliq\Controllers\GuestTestController::TRACK_MAX_PER_IP ?> every 15 minutes per connection.</p>
    </aside>
  </div>
</div>
