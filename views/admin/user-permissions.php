<?php
use Vitaliq\Support\Labels;
?>
<div class="app-head">
  <div>
    <h1><?= e($subject['full_name']) ?></h1>
    <p><?= e(Labels::get(Labels::ROLES, $subject['role'])) ?> · <?= e($subject['email']) ?></p>
  </div>
  <a href="<?= e(url('/admin/roles')) ?>">All roles</a>
</div>
<?php if ($subject['role'] === 'admin'): ?>
  <p class="callout callout--note">Administrators always hold every permission.</p>
<?php else: ?>
<form method="post" action="<?= e(url('/admin/users/' . (int) $subject['id'] . '/permissions')) ?>">
  <?= csrf_field() ?>
  <section class="panel">
    <table class="table">
      <thead><tr><th scope="col">Permission</th><th scope="col">Now</th><th scope="col">Setting for this person</th></tr></thead>
      <tbody>
        <?php foreach ($permissions as $perm => $desc): ?>
          <?php $o = $overrides[$perm]; ?>
          <tr>
            <th scope="row"><span class="ref"><?= e($perm) ?></span><span class="sub"><?= e($desc) ?></span></th>
            <td><?= !empty($effective[$perm]) ? '<span class="status status--ok">Allowed</span>' : '<span class="status status--cancelled">Not allowed</span>' ?></td>
            <td>
              <label class="visually-hidden" for="p-<?= e($perm) ?>"><?= e($perm) ?></label>
              <select class="input" id="p-<?= e($perm) ?>" name="perm[<?= e($perm) ?>]">
                <option value="inherit"<?= selected($o === null ? 'inherit' : '', 'inherit') ?>>Follow the role</option>
                <option value="grant"<?= selected($o === true ? 'grant' : '', 'grant') ?>>Allow</option>
                <option value="deny"<?= selected($o === false ? 'deny' : '', 'deny') ?>>Do not allow</option>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="form-actions"><button class="btn btn--primary" type="submit">Save</button></div>
  </section>
</form>
<?php endif; ?>
