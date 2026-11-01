<?php
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Services\PatientService;
use Vitaliq\Support\Labels;

$chosen = array_map('intval', is_array($old['tests'] ?? null) ? $old['tests'] : []);
?>
<div class="app-head">
  <div>
    <h1>New test order</h1>
    <p>Find the patient, choose the tests, and print one label per specimen type.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<section class="panel" aria-labelledby="p-find">
  <div class="panel__head"><h2 id="p-find">1. Patient</h2></div>
  <form method="get" action="<?= e(url('/lab/orders/new')) ?>" class="cluster">
    <label class="visually-hidden" for="f-q">Name, mobile or testing reference</label>
    <input class="input" id="f-q" name="q" value="<?= e($q) ?>" minlength="3" maxlength="60" placeholder="Name, mobile or VQ reference">
    <button class="btn btn--secondary" type="submit">Find</button>
  </form>
  <?php if ($q !== '' && mb_strlen($q) < 3): ?><p class="hint mt-4">Type at least 3 characters.</p><?php endif; ?>
</section>

<?php if ($matches !== [] || isset($old['patient_id'])): ?>
<form method="post" action="<?= e(url('/lab/orders/new')) ?>" novalidate>
  <?= csrf_field() ?><input type="hidden" name="q" value="<?= e($q) ?>">
  <section class="panel" aria-labelledby="p-pick" id="f-patient_id">
    <div class="panel__head"><h2 id="p-pick">Matching patients</h2><span class="meta"><?= count($matches) ?> found</span></div>
    <?php if ($matches === []): ?>
      <p class="empty">No patient matches “<?= e($q) ?>”.</p>
    <?php else: ?>
      <div class="choice-grid">
        <?php foreach ($matches as $m): ?>
          <label class="choice"><input type="radio" name="patient_id" value="<?= (int) $m['id'] ?>"<?= checked((string) ($old['patient_id'] ?? '') === (string) $m['id'] || count($matches) === 1) ?>>
            <span class="choice__text"><?= e($m['full_name']) ?><small><?= PatientService::age((string) $m['date_of_birth'], $now) ?> y · <?= e(Labels::get(Labels::SEX, $m['sex'])) ?> · <?= e(format_mobile($m['mobile'])) ?></small></span></label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?= field_error($errors, 'patient_id') ?>
  </section>

  <section class="panel" aria-labelledby="t-pick" id="f-tests">
    <div class="panel__head"><h2 id="t-pick">2. Tests</h2></div>
    <?php foreach ($tests as $section => $list): ?>
      <fieldset class="mb-0 mt-4">
        <legend><?= e(LaboratoryService::SECTIONS[$section]) ?></legend>
        <div class="choice-grid">
          <?php foreach ($list as $t): ?>
            <label class="choice"><input type="checkbox" name="tests[]" value="<?= (int) $t['id'] ?>"<?= checked(in_array((int) $t['id'], $chosen, true)) ?>>
              <span class="choice__text"><?= e($t['name']) ?><small><?= e($t['code']) ?> · <?= e(LaboratoryService::SPECIMENS[$t['specimen']]) ?> · <?= (int) $t['turnaround_hours'] ?> h</small></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
    <?= field_error($errors, 'tests') ?>
  </section>

  <section class="panel" aria-labelledby="o-det">
    <div class="panel__head"><h2 id="o-det">3. Details</h2></div>
    <div class="form-grid form-grid--2">
      <div class="field"><label for="f-priority">Priority</label>
        <select class="input" id="f-priority" name="priority"><?php foreach (LaboratoryService::PRIORITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected((string) ($old['priority'] ?? 'routine'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field span-all"><label for="f-notes">Clinical notes <span class="optional">optional</span></label><textarea class="input" id="f-notes" name="clinical_notes" rows="2" maxlength="500"><?= e(old($old, 'clinical_notes')) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="btn btn--primary" type="submit" data-busy-label="Creating…">Create order</button></div>
  </section>
</form>
<?php endif; ?>
