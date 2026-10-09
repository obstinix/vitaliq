<div class="app-head">
  <div>
    <p class="eyebrow">Your care</p>
    <h1>Link an earlier request</h1>
    <p>If you requested a test before you registered, add it to your history here.</p>
  </div>
</div>
<div class="grid-2">
  <div>
    <?php if (isset($errors['form'])): ?><div class="callout callout--urgent mb-5" role="alert"><p><?= e($errors['form']) ?></p></div><?php endif; ?>
    <form class="form stack" method="post" action="<?= e(url('/patient/link')) ?>" novalidate data-validate data-once>
      <?= csrf_field() ?>
      <div class="field">
        <label for="f-reference">Reference</label>
        <input class="input input--mono" id="f-reference" name="reference" maxlength="15" required placeholder="VQ-261010-7K3MP" data-rule="reference" value="<?= e(old($old, 'reference')) ?>"<?= aria_invalid($errors, 'reference') ?>>
        <?= field_error($errors, 'reference') ?>
      </div>
      <div class="field">
        <label for="f-date_of_birth">Date of birth given on that request</label>
        <input class="input" id="f-date_of_birth" name="date_of_birth" type="date" required data-rule="iso_date" value="<?= e(old($old, 'date_of_birth')) ?>"<?= aria_invalid($errors, 'date_of_birth') ?>>
        <?= field_error($errors, 'date_of_birth') ?>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Link request</button></div>
    </form>
  </div>
  <aside class="callout callout--note">
    <h2>How matching works</h2>
    <p>The request must have been made without an account, using the same mobile number as your profile. The date of birth must match too. This stops anyone attaching another person’s results to their account.</p>
  </aside>
</div>
