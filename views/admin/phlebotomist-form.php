<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/admin/phlebotomists')) ?>">Phlebotomists</a></p>
    <h1><?= e($title) ?></h1>
    <?php if ($record !== null): ?>
      <p><span class="mono"><?= e($record['employee_code']) ?></span> · <span class="status status--<?= (int) $record['is_active'] === 1 ? 'pass' : 'blocked' ?>"><?= (int) $record['is_active'] === 1 ? 'Active' : 'Inactive' ?></span> · <?= (int) $openCollections ?> open collection<?= $openCollections === 1 ? '' : 's' ?></p>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div>
    <?= $view->partial('error-summary', ['errors' => $errors]) ?>
    <form class="form" method="post" action="<?= e(url($record === null ? '/admin/phlebotomists/new' : '/admin/phlebotomists/' . (int) $record['id'])) ?>" novalidate data-validate data-once>
      <?= csrf_field() ?>
      <div class="form-grid form-grid--2">
        <div class="field span-2">
          <label for="f-full_name">Full name</label>
          <input class="input" id="f-full_name" name="full_name" maxlength="100" required data-rule="full_name" value="<?= e(old($old, 'full_name')) ?>"<?= aria_invalid($errors, 'full_name') ?>>
          <?= field_error($errors, 'full_name') ?>
        </div>
        <div class="field">
          <label for="f-employee_code">Employee code <span class="optional">(<?= $record === null ? 'leave blank to generate' : 'optional' ?>)</span></label>
          <input class="input input--mono" id="f-employee_code" name="employee_code" maxlength="8" placeholder="PHL-0000" data-rule="employee_code" data-optional value="<?= e(old($old, 'employee_code')) ?>"<?= aria_invalid($errors, 'employee_code') ?>>
          <?= field_error($errors, 'employee_code') ?>
        </div>
        <div class="field">
          <label for="f-mobile">Mobile number</label>
          <input class="input input--mono" id="f-mobile" name="mobile" type="tel" maxlength="16" required data-rule="indian_mobile" value="<?= e(old($old, 'mobile')) ?>"<?= aria_invalid($errors, 'mobile') ?>>
          <?= field_error($errors, 'mobile') ?>
        </div>
        <div class="field span-2">
          <label for="f-email">Email <span class="optional">(optional)</span></label>
          <input class="input" id="f-email" name="email" type="email" maxlength="254" data-rule="email" data-optional value="<?= e(old($old, 'email')) ?>"<?= aria_invalid($errors, 'email') ?>>
          <?= field_error($errors, 'email') ?>
        </div>
        <div class="field">
          <label for="f-base_city">Base city</label>
          <input class="input" id="f-base_city" name="base_city" maxlength="80" required value="<?= e(old($old, 'base_city')) ?>"<?= aria_invalid($errors, 'base_city') ?>>
          <?= field_error($errors, 'base_city') ?>
        </div>
        <div class="field">
          <label for="f-state_code">State / UT</label>
          <select class="input" id="f-state_code" name="state_code" required<?= aria_invalid($errors, 'state_code') ?>>
            <option value="">Choose</option>
            <?php foreach ($states as $code => $name): ?><option value="<?= e($code) ?>"<?= selected(old($old, 'state_code'), (string) $code) ?>><?= e($name) ?></option><?php endforeach; ?>
          </select>
          <?= field_error($errors, 'state_code') ?>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= $record === null ? 'Add phlebotomist' : 'Save changes' ?></button>
        <a href="<?= e(url('/admin/phlebotomists')) ?>">Back to list</a>
      </div>
    </form>
  </div>

  <?php if ($record !== null): ?>
    <aside class="stack">
      <section class="panel" aria-labelledby="status-title">
        <div class="panel__head"><h2 id="status-title">Availability</h2></div>
        <form method="post" action="<?= e(url('/admin/phlebotomists/' . (int) $record['id'] . '/toggle')) ?>">
          <?= csrf_field() ?>
          <p><?= (int) $record['is_active'] === 1 ? 'Active phlebotomists can be assigned new collections.' : 'Inactive phlebotomists cannot be assigned.' ?></p>
          <button class="btn btn--secondary btn--small" type="submit"><?= (int) $record['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
        </form>
        <p class="hint mt-4">To give this phlebotomist a portal login, <a href="<?= e(url('/admin/users/new')) ?>">invite a staff member</a> with the Phlebotomist role.</p>
      </section>
      <details class="confirm confirm--danger">
        <summary>Remove this record</summary>
        <div class="confirm__body">
          <p class="small">If this phlebotomist appears in any testing history, the record is archived (hidden and deactivated) instead of deleted, so past requests keep their audit trail.</p>
          <form method="post" action="<?= e(url('/admin/phlebotomists/' . (int) $record['id'] . '/delete')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn--danger btn--small" type="submit">Remove</button>
          </form>
        </div>
      </details>
    </aside>
  <?php endif; ?>
</div>
