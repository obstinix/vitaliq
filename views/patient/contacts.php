<div class="app-head">
  <div>
    <h1>Emergency contacts</h1>
    <p>People to call if <?= $profile['rel'] === 'self' ? 'you need' : e($profile['full_name']) . ' needs' ?> help. The primary contact is listed first on the QR emergency card.</p>
  </div>
</div>
<?= $view->partial('profile-switch', ['profiles' => $profiles, 'profile' => $profile, 'base' => '/patient/contacts']) ?>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="panel span-7" aria-labelledby="ec-list">
    <div class="panel__head"><h2 id="ec-list">Contacts</h2><span class="meta"><?= count($contacts) ?> of 5</span></div>
    <?php if ($contacts === []): ?>
      <p class="empty">No emergency contacts yet.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($contacts as $c): ?>
          <li>
            <span class="grow"><?= e($c['full_name']) ?> <?= (int) $c['is_primary'] === 1 ? '<span class="status status--active">Primary</span>' : '' ?><span class="sub"><?= e($c['relationship']) ?></span></span>
            <span class="num"><?= e(format_mobile($c['mobile'])) ?></span>
            <?php if ((int) $c['is_primary'] !== 1): ?>
              <form method="post" action="<?= e(url('/patient/contacts/' . (int) $c['id'] . '/primary')) ?>"><?= csrf_field() ?><?= profile_field($profile) ?><button class="btn btn--ghost btn--small" type="submit">Make primary</button></form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/patient/contacts/' . (int) $c['id'] . '/delete')) ?>"><?= csrf_field() ?><?= profile_field($profile) ?><button class="btn btn--ghost btn--small" type="submit" aria-label="Remove <?= e($c['full_name']) ?>">Remove</button></form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <section class="panel span-5" aria-labelledby="ec-add">
    <div class="panel__head"><h2 id="ec-add">Add a contact</h2></div>
    <form method="post" action="<?= e(url('/patient/contacts')) ?>" novalidate>
      <?= csrf_field() ?><?= profile_field($profile) ?>
      <div class="form-grid">
        <div class="field"><label for="f-full_name">Name</label><input class="input" id="f-full_name" name="full_name" maxlength="100" value="<?= e(old($old, 'full_name')) ?>"<?= aria_invalid($errors, 'full_name') ?>><?= field_error($errors, 'full_name') ?></div>
        <div class="field"><label for="f-relationship">Relationship</label><input class="input" id="f-relationship" name="relationship" maxlength="40" value="<?= e(old($old, 'relationship')) ?>"<?= aria_invalid($errors, 'relationship') ?>><?= field_error($errors, 'relationship') ?></div>
        <div class="field"><label for="f-mobile">Mobile</label><input class="input" id="f-mobile" name="mobile" inputmode="tel" value="<?= e(old($old, 'mobile')) ?>"<?= aria_invalid($errors, 'mobile') ?>><?= field_error($errors, 'mobile') ?></div>
        <label class="checkbox-line"><input type="checkbox" name="is_primary" value="1"<?= checked(old($old, 'is_primary') === '1') ?>> <span>Make this the primary contact</span></label>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Add contact</button></div>
    </form>
  </section>
</div>
