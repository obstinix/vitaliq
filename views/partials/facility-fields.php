<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label for="f-code">Code</label><input class="input input--mono" id="f-code" name="code" maxlength="12" value="<?= e((string) ($f['code'] ?? '')) ?>"<?= aria_invalid($errors, 'code') ?>><?= field_error($errors, 'code') ?></div>
    <div class="field"><label for="f-name">Name</label><input class="input" id="f-name" name="name" maxlength="120" value="<?= e((string) ($f['name'] ?? '')) ?>"<?= aria_invalid($errors, 'name') ?>><?= field_error($errors, 'name') ?></div>
    <div class="field"><label for="f-kind">Type</label><select class="input" id="f-kind" name="kind"<?= aria_invalid($errors, 'kind') ?>><?php foreach ($kinds as $k => $l): ?><option value="<?= e($k) ?>"<?= selected((string) ($f['kind'] ?? ''), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'kind') ?></div>
    <div class="field"><label for="f-city">City</label><input class="input" id="f-city" name="city" maxlength="80" value="<?= e((string) ($f['city'] ?? '')) ?>"<?= aria_invalid($errors, 'city') ?>><?= field_error($errors, 'city') ?></div>
    <div class="field"><label for="f-state_code">State</label><select class="input" id="f-state_code" name="state_code"<?= aria_invalid($errors, 'state_code') ?>><option value="">Choose</option><?php foreach ($states as $k => $l): ?><option value="<?= e($k) ?>"<?= selected((string) ($f['state_code'] ?? ''), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'state_code') ?></div>
  </div>
  <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e($button) ?></button></div>
</form>
