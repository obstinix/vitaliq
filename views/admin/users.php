<?php use Vitaliq\Support\Labels; ?>
<div class="app-head">
  <div>
    <p class="eyebrow">Governance</p>
    <h1>Users</h1>
    <p>Activate or deactivate accounts, and invite staff. Passwords and hashes are never shown or exported.</p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/admin/users/new')) ?>">Invite staff member</a>
</div>

<?php if (!empty($inviteLink)): ?>
  <div class="callout callout--success mb-5" role="status">
    <h2>One-time set-password link</h2>
    <p>Shown once. Valid for 48 hours and usable once. Share it only with the new staff member.</p>
    <p class="mb-0"><code class="input-echo" data-copy><?= e($inviteLink) ?></code></p>
  </div>
<?php endif; ?>

<form class="filter-bar" method="get" action="<?= e(url('/admin/users')) ?>" role="search">
  <div class="field">
    <label for="u-q">Name or email</label>
    <input class="input" id="u-q" name="q" maxlength="80" value="<?= e($search) ?>">
  </div>
  <div class="field">
    <label for="u-role">Role</label>
    <select class="input" id="u-role" name="role">
      <option value="">Any role</option>
      <?php foreach (Labels::ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($role, $k) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="filter-bar__actions"><button class="btn btn--primary btn--small" type="submit">Filter</button></div>
</form>

<?php if ($list['rows'] === []): ?>
  <div class="empty"><h2>No users found</h2><p>Try a different name or role.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Last sign-in</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>
        <?php foreach ($list['rows'] as $u): ?>
          <tr>
            <td data-label="Name"><?= e($u['full_name']) ?><?= (int) $u['id'] === (int) $currentUser['id'] ? ' <span class="tag">You</span>' : '' ?></td>
            <td data-label="Email"><?= e($u['email']) ?></td>
            <td data-label="Role"><?= e(Labels::get(Labels::ROLES, $u['role'])) ?></td>
            <td data-label="Last sign-in"><?= e(fmt_datetime($u['last_login_at'])) ?></td>
            <td data-label="Status">
              <span class="status status--<?= (int) $u['is_active'] === 1 ? 'pass' : 'blocked' ?>"><?= (int) $u['is_active'] === 1 ? 'Active' : 'Inactive' ?></span>
              <?php if ((int) $u['has_password'] === 0): ?><br><span class="small muted">Invitation pending</span><?php endif; ?>
            </td>
            <td data-label="Actions">
              <?php if ((int) $u['id'] !== (int) $currentUser['id']): ?>
                <form class="inline-form" method="post" action="<?= e(url('/admin/users/' . (int) $u['id'] . '/toggle')) ?>">
                  <?= csrf_field() ?>
                  <button class="btn btn--secondary btn--small" type="submit"><?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                </form>
              <?php endif; ?>
              <?php if ((int) $u['has_password'] === 0 && $u['role'] !== 'patient' && (int) $u['is_active'] === 1): ?>
                <form class="inline-form" method="post" action="<?= e(url('/admin/users/' . (int) $u['id'] . '/reinvite')) ?>">
                  <?= csrf_field() ?>
                  <button class="btn btn--ghost btn--small" type="submit">New link</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $view->partial('pagination', ['total' => $list['total'], 'page' => $page, 'perPage' => 25, 'path' => '/admin/users', 'query' => ['q' => $search, 'role' => $role]]) ?>
<?php endif; ?>
