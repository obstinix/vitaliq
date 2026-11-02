<div class="app-head">
  <div>
    <h1>Roles and permissions</h1>
    <p>Each role starts with a default bundle (marked “default”). Tick or untick to change it for everyone in that role. Administrators always hold every permission.</p>
  </div>
</div>
<form method="post" action="<?= e(url('/admin/roles')) ?>">
  <?= csrf_field() ?>
  <section class="panel">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th scope="col">Permission</th><?php foreach ($roles as $k => $l): ?><th scope="col"><?= e($l) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($permissions as $perm => $desc): ?>
            <tr>
              <th scope="row"><span class="ref"><?= e($perm) ?></span><span class="sub"><?= e($desc) ?></span></th>
              <?php foreach ($roles as $role => $label): ?>
                <?php $s = $matrix[$role][$perm]; ?>
                <td><label class="checkbox-line"><input type="checkbox" name="grant[<?= e($role) ?>][<?= e($perm) ?>]" value="1"<?= checked($s['effective']) ?>>
                  <span class="sub"><?= $s['override'] === null ? ($s['default'] ? 'default' : '') : ($s['override'] ? 'granted' : 'revoked') ?></span>
                  <span class="visually-hidden"><?= e($label . ': ' . $perm) ?></span></label></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="form-actions"><button class="btn btn--primary" type="submit">Save permissions</button></div>
  </section>
</form>
<section class="panel" aria-labelledby="per-user">
  <div class="panel__head"><h2 id="per-user">One person at a time</h2><span class="meta">A person's own settings win over their role</span></div>
  <?php if ($staff === []): ?>
    <p class="empty">No staff accounts other than administrators.</p>
  <?php else: ?>
    <ul class="rows">
      <?php foreach ($staff as $s): ?>
        <li><span class="grow"><?= e($s['full_name']) ?><span class="sub"><?= e($roles[$s['role']] ?? $s['role']) ?></span></span><a href="<?= e(url('/admin/users/' . (int) $s['id'] . '/permissions')) ?>">Adjust</a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
