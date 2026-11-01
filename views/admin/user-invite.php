<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/admin/users')) ?>">Users</a></p>
    <h1>Invite a staff member</h1>
    <p>The account is created without a password. You receive a one-time link for the person to set their own password.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<form class="form stack" method="post" action="<?= e(url('/admin/users/new')) ?>" novalidate data-validate data-once>
  <?= csrf_field() ?>
  <div class="field">
    <label for="f-role">Role</label>
    <select class="input" id="f-role" name="role" required data-role-select<?= aria_invalid($errors, 'role') ?>>
      <option value="">Choose</option>
      <option value="admin"<?= selected(old($old, 'role'), 'admin') ?>>Administrator</option>
      <option value="qa"<?= selected(old($old, 'role'), 'qa') ?>>QA analyst</option>
      <option value="phlebotomist"<?= selected(old($old, 'role'), 'phlebotomist') ?>>Phlebotomist</option>
      <option value="lab"<?= selected(old($old, 'role'), 'lab') ?>>Laboratory staff</option>
    </select>
    <?= field_error($errors, 'role') ?>
  </div>
  <div class="field" data-show-when="role-phlebotomist">
    <label for="f-phlebotomist_id">Phlebotomist record</label>
    <select class="input" id="f-phlebotomist_id" name="phlebotomist_id"<?= aria_invalid($errors, 'phlebotomist_id') ?>>
      <option value="">Choose</option>
      <?php foreach ($phlebotomists as $id => $label): ?><option value="<?= (int) $id ?>"<?= selected(old($old, 'phlebotomist_id'), (string) $id) ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <p class="hint">Only records without a portal login are listed.</p>
    <?= field_error($errors, 'phlebotomist_id') ?>
  </div>
  <div class="field">
    <label for="f-full_name">Full name</label>
    <input class="input" id="f-full_name" name="full_name" maxlength="100" required data-rule="full_name" value="<?= e(old($old, 'full_name')) ?>"<?= aria_invalid($errors, 'full_name') ?>>
    <?= field_error($errors, 'full_name') ?>
  </div>
  <div class="field">
    <label for="f-email">Work email</label>
    <input class="input" id="f-email" name="email" type="email" maxlength="254" required data-rule="email" value="<?= e(old($old, 'email')) ?>"<?= aria_invalid($errors, 'email') ?>>
    <?= field_error($errors, 'email') ?>
  </div>
  <div class="form-actions">
    <button class="btn btn--primary" type="submit">Create account and link</button>
    <a href="<?= e(url('/admin/users')) ?>">Cancel</a>
  </div>
</form>
