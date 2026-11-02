<?php
use Vitaliq\Support\Labels;

$assigned = array_map(static fn ($s) => (int) $s['id'], $staff);
$open = (int) $f['is_active'] === 1;
?>
<div class="app-head">
  <div>
    <h1><?= e($f['name']) ?></h1>
    <p><span class="ref"><?= e($f['code']) ?></span> · <?= e($kinds[$f['kind']]) ?> · <?= e($f['city']) ?></p>
  </div>
  <form method="post" action="<?= e(url('/admin/facilities/' . (int) $f['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn <?= $open ? 'btn--danger' : 'btn--secondary' ?>" type="submit"><?= $open ? 'Close facility' : 'Reopen facility' ?></button></form>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="panel span-6" aria-labelledby="det">
    <div class="panel__head"><h2 id="det">Details</h2></div>
    <?= $view->partial('facility-fields', ['action' => url('/admin/facilities/' . (int) $f['id']), 'f' => $old !== [] ? $old : $f, 'kinds' => $kinds, 'states' => $states, 'errors' => $errors, 'button' => 'Save']) ?>
  </section>
  <section class="panel span-6" aria-labelledby="stf">
    <div class="panel__head"><h2 id="stf">Staff</h2><span class="meta"><?= count($staff) ?></span></div>
    <?php if ($staff === []): ?>
      <p class="empty">Nobody is assigned here yet.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($staff as $s): ?>
          <li><span class="grow"><?= e($s['full_name']) ?><span class="sub"><?= e(Labels::get(Labels::ROLES, $s['role'])) ?></span></span>
            <form method="post" action="<?= e(url('/admin/facilities/' . (int) $f['id'] . '/staff')) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $s['id'] ?>"><button class="btn btn--ghost btn--small" name="action" value="remove" type="submit">Remove</button></form></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/admin/facilities/' . (int) $f['id'] . '/staff')) ?>" class="cluster mt-4">
      <?= csrf_field() ?>
      <label class="visually-hidden" for="f-user">Staff member</label>
      <select class="input" id="f-user" name="user_id">
        <?php foreach ($candidates as $c): ?><?php if (!in_array((int) $c['id'], $assigned, true)): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['full_name'] . ' (' . Labels::get(Labels::ROLES, $c['role']) . ')') ?></option><?php endif; ?><?php endforeach; ?>
      </select>
      <button class="btn btn--secondary" name="action" value="add" type="submit">Assign</button>
    </form>
  </section>
</div>
