<?php
use Vitaliq\Services\PatientPlatformService;
use Vitaliq\Support\Labels;

$hp = $passport['profile'];
$o = $old !== [] ? $old : $hp;
$self = $profile['rel'] === 'self';
?>
<div class="app-head">
  <div>
    <h1><?= e($self ? 'Health passport' : $profile['full_name']) ?></h1>
    <p><?= e($profile['full_name']) ?> · <?= (int) $age ?> years · <?= e(Labels::get(Labels::SEX, $profile['sex'])) ?><?= $hp['updated_at'] ? ' · updated ' . e(fmt_date($hp['updated_at'])) : '' ?></p>
  </div>
  <div class="cluster">
    <a class="btn btn--secondary" href="<?= e(url('/patient/health-id', profile_query($profile))) ?>">QR health ID</a>
    <a class="btn btn--secondary" href="<?= e(url('/patient/timeline', profile_query($profile))) ?>">Timeline</a>
  </div>
</div>
<?= $view->partial('profile-switch', ['profiles' => $profiles, 'profile' => $profile, 'base' => '/patient/passport']) ?>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>

<section class="kpis" aria-label="Summary">
  <div class="kpi"><span class="kpi__value"><?= $hp['blood_group'] === 'unknown' ? '<span class="faint">—</span>' : e($hp['blood_group']) ?></span><span class="kpi__label">Blood group</span></div>
  <div class="kpi"><span class="kpi__value"><?= $hp['height_cm'] ? (int) $hp['height_cm'] . '<small>cm</small>' : '<span class="faint">—</span>' ?></span><span class="kpi__label">Height</span></div>
  <div class="kpi"><span class="kpi__value"><?= $hp['weight_kg'] ? e(number_format((float) $hp['weight_kg'], 1)) . '<small>kg</small>' : '<span class="faint">—</span>' ?></span><span class="kpi__label">Weight</span></div>
  <div class="kpi"><span class="kpi__value"><?= count($passport['immunisations']) ?></span><span class="kpi__label">Immunisations recorded</span></div>
</section>

<div class="vq-grid">
  <section class="panel span-7" aria-labelledby="pp-edit">
    <div class="panel__head"><h2 id="pp-edit">Medical summary</h2><span class="meta">Shown on your QR emergency card</span></div>
    <form method="post" action="<?= e(url('/patient/passport')) ?>" class="form form--wide" novalidate>
      <?= csrf_field() ?><?= profile_field($profile) ?>
      <div class="form-grid form-grid--3">
        <div class="field">
          <label for="f-blood_group">Blood group</label>
          <select class="input" id="f-blood_group" name="blood_group"<?= aria_invalid($errors, 'blood_group') ?>>
            <?php foreach (PatientPlatformService::BLOOD_GROUPS as $g): ?>
              <option value="<?= e($g) ?>"<?= selected((string) ($o['blood_group'] ?? 'unknown'), $g) ?>><?= e($g === 'unknown' ? 'Not known' : $g) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'blood_group') ?>
        </div>
        <div class="field">
          <label for="f-height_cm">Height <span class="optional">cm</span></label>
          <input class="input" id="f-height_cm" name="height_cm" inputmode="numeric" value="<?= e((string) ($o['height_cm'] ?? '')) ?>"<?= aria_invalid($errors, 'height_cm') ?>>
          <?= field_error($errors, 'height_cm') ?>
        </div>
        <div class="field">
          <label for="f-weight_kg">Weight <span class="optional">kg</span></label>
          <input class="input" id="f-weight_kg" name="weight_kg" inputmode="decimal" value="<?= e((string) ($o['weight_kg'] ?? '')) ?>"<?= aria_invalid($errors, 'weight_kg') ?>>
          <?= field_error($errors, 'weight_kg') ?>
        </div>
        <div class="field span-all">
          <label for="f-allergies">Allergies <span class="optional">optional</span></label>
          <textarea class="input" id="f-allergies" name="allergies" rows="2" maxlength="500"<?= aria_invalid($errors, 'allergies') ?>><?= e((string) ($o['allergies'] ?? '')) ?></textarea>
          <?= field_error($errors, 'allergies') ?>
        </div>
        <div class="field span-all">
          <label for="f-conditions">Long-term conditions <span class="optional">optional</span></label>
          <textarea class="input" id="f-conditions" name="conditions" rows="2" maxlength="500"><?= e((string) ($o['conditions'] ?? '')) ?></textarea>
          <?= field_error($errors, 'conditions') ?>
        </div>
        <div class="field span-all">
          <label for="f-medications">Current medicines <span class="optional">optional</span></label>
          <textarea class="input" id="f-medications" name="medications" rows="2" maxlength="500"><?= e((string) ($o['medications'] ?? '')) ?></textarea>
          <?= field_error($errors, 'medications') ?>
        </div>
        <div class="field">
          <label for="f-organ_donor">Organ donor</label>
          <select class="input" id="f-organ_donor" name="organ_donor">
            <?php foreach (['undecided' => 'Not decided', 'yes' => 'Yes', 'no' => 'No'] as $k => $l): ?>
              <option value="<?= e($k) ?>"<?= selected((string) ($o['organ_donor'] ?? 'undecided'), $k) ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Save passport</button></div>
    </form>
  </section>

  <div class="span-5 stack">
    <section class="panel" aria-labelledby="pp-imm">
      <div class="panel__head"><h2 id="pp-imm">Immunisations</h2><span class="meta"><?= count($passport['immunisations']) ?> recorded</span></div>
      <?php if ($passport['immunisations'] === []): ?>
        <p class="empty">No immunisations recorded yet.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($passport['immunisations'] as $i): ?>
            <li>
              <span class="grow"><?= e($i['vaccine']) ?><?= $i['dose_label'] ? ' · ' . e($i['dose_label']) : '' ?><span class="sub"><?= e($i['provider'] ?? '—') ?></span></span>
              <time class="end num" datetime="<?= e($i['given_on']) ?>"><?= e($i['given_on']) ?></time>
              <form method="post" action="<?= e(url('/patient/passport/immunisations/' . (int) $i['id'] . '/delete')) ?>">
                <?= csrf_field() ?><?= profile_field($profile) ?>
                <button class="btn btn--ghost btn--small" type="submit" aria-label="Remove <?= e($i['vaccine']) ?>">Remove</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <details class="confirm mt-4">
        <summary>Add an immunisation</summary>
        <div class="confirm__body">
          <form method="post" action="<?= e(url('/patient/passport/immunisations')) ?>" novalidate>
            <?= csrf_field() ?><?= profile_field($profile) ?>
            <div class="form-grid form-grid--2">
              <div class="field span-all"><label for="f-vaccine">Vaccine</label><input class="input" id="f-vaccine" name="vaccine" maxlength="80" value="<?= e(old($old, 'vaccine')) ?>"<?= aria_invalid($errors, 'vaccine') ?>><?= field_error($errors, 'vaccine') ?></div>
              <div class="field"><label for="f-dose_label">Dose <span class="optional">optional</span></label><input class="input" id="f-dose_label" name="dose_label" maxlength="30" value="<?= e(old($old, 'dose_label')) ?>"></div>
              <div class="field"><label for="f-given_on">Date given</label><input class="input" type="date" id="f-given_on" name="given_on" max="<?= e($today) ?>" value="<?= e(old($old, 'given_on')) ?>"<?= aria_invalid($errors, 'given_on') ?>><?= field_error($errors, 'given_on') ?></div>
              <div class="field span-all"><label for="f-provider">Given by <span class="optional">optional</span></label><input class="input" id="f-provider" name="provider" maxlength="120" value="<?= e(old($old, 'provider')) ?>"></div>
            </div>
            <div class="form-actions"><button class="btn btn--secondary" type="submit">Add immunisation</button></div>
          </form>
        </div>
      </details>
    </section>

    <section class="panel" aria-labelledby="pp-res">
      <div class="panel__head"><h2 id="pp-res">Latest released results</h2></div>
      <?php if ($passport['results'] === []): ?>
        <p class="empty">No released results on this profile.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($passport['results'] as $r): ?>
            <li><span class="grow"><span class="ref"><?= e($r['reference']) ?></span><span class="sub"><?= e(Labels::get(Labels::TEST_TYPES, $r['test_type'])) ?></span></span>
              <span class="end"><span class="outcome outcome--<?= e($r['outcome']) ?>"><?= e(ucfirst((string) $r['outcome'])) ?></span><span class="sub"><?= e(fmt_date($r['released_at'])) ?></span></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="pp-con">
      <div class="panel__head"><h2 id="pp-con">Emergency contacts</h2><a href="<?= e(url('/patient/contacts', profile_query($profile))) ?>">Manage</a></div>
      <?php if ($passport['contacts'] === []): ?>
        <p class="empty">No emergency contact yet.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($passport['contacts'] as $c): ?>
            <li><span class="grow"><?= e($c['full_name']) ?><span class="sub"><?= e($c['relationship']) ?><?= (int) $c['is_primary'] === 1 ? ' · primary' : '' ?></span></span><span class="end num"><?= e(format_mobile($c['mobile'])) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
