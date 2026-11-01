<?php
use Vitaliq\Support\Labels;

$family = array_values(array_filter($profiles, static fn ($p) => $p['rel'] !== 'self'));
?>
<div class="app-head">
  <div>
    <h1>Family profiles</h1>
    <p><?= count($family) === 0 ? 'Keep records and book appointments for the people you care for.' : count($family) . ' ' . (count($family) === 1 ? 'profile' : 'profiles') . ' managed from your account.' ?></p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="panel span-7" aria-labelledby="fam-list">
    <div class="panel__head"><h2 id="fam-list">People you manage</h2></div>
    <?php if ($family === []): ?>
      <p class="empty">No family profiles yet. Add one with the form beside this list.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Name</th><th scope="col">Relationship</th><th scope="col" class="num">Date of birth</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
          <tbody>
            <?php foreach ($family as $f): ?>
              <tr>
                <td data-label="Name"><a href="<?= e(url('/patient/passport', ['for' => (int) $f['id']])) ?>"><?= e($f['full_name']) ?></a><span class="sub"><?= e(Labels::get(Labels::SEX, $f['sex'])) ?></span></td>
                <td data-label="Relationship"><?= e($relationships[$f['rel']] ?? 'Other') ?></td>
                <td data-label="Date of birth" class="num"><?= e($f['date_of_birth']) ?></td>
                <td data-label="Actions">
                  <details class="confirm confirm--danger">
                    <summary>Remove</summary>
                    <div class="confirm__body">
                      <p class="small">Removes <?= e($f['full_name']) ?> from your account. Their test history stays on record.</p>
                      <form method="post" action="<?= e(url('/patient/family/' . (int) $f['id'] . '/remove')) ?>"><?= csrf_field() ?><button class="btn btn--danger btn--small" type="submit">Remove <?= e($f['full_name']) ?></button></form>
                    </div>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <section class="panel span-5" aria-labelledby="fam-add">
    <div class="panel__head"><h2 id="fam-add">Add a family member</h2></div>
    <form method="post" action="<?= e(url('/patient/family')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label for="f-full_name">Full name</label><input class="input" id="f-full_name" name="full_name" maxlength="100" autocomplete="off" value="<?= e(old($old, 'full_name')) ?>"<?= aria_invalid($errors, 'full_name') ?>><?= field_error($errors, 'full_name') ?></div>
        <div class="field"><label for="f-relationship">Relationship</label>
          <select class="input" id="f-relationship" name="relationship"<?= aria_invalid($errors, 'relationship') ?>>
            <option value="">Choose</option>
            <?php foreach ($relationships as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'relationship'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
          </select><?= field_error($errors, 'relationship') ?></div>
        <div class="field"><label for="f-date_of_birth">Date of birth</label><input class="input" type="date" id="f-date_of_birth" name="date_of_birth" max="<?= e($today) ?>" value="<?= e(old($old, 'date_of_birth')) ?>"<?= aria_invalid($errors, 'date_of_birth') ?>><?= field_error($errors, 'date_of_birth') ?></div>
        <div class="field"><label for="f-sex">Sex</label>
          <select class="input" id="f-sex" name="sex">
            <?php foreach (Labels::SEX as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'sex', 'undisclosed'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label for="f-mobile">Mobile <span class="optional">optional, defaults to yours</span></label><input class="input" id="f-mobile" name="mobile" inputmode="tel" value="<?= e(old($old, 'mobile')) ?>"<?= aria_invalid($errors, 'mobile') ?>><?= field_error($errors, 'mobile') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Add profile</button></div>
    </form>
  </section>
</div>
