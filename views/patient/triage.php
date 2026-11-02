<?php
use Vitaliq\Services\ClinicalReferenceService;

$chosen = static fn (string $k): array => is_array($old[$k] ?? null) ? array_map('strval', $old[$k]) : [];
$o = $result['outcome'] ?? null;
?>
<div class="app-head">
  <div>
    <h1><?= e(t('Check symptoms')) ?></h1>
    <p>A short questionnaire that suggests what to do next. It does not tell you what illness you have.</p>
  </div>
</div>
<?php if ($result !== null): ?>
  <section class="panel triage-result triage-result--<?= e($o) ?>" aria-live="polite" aria-labelledby="tr">
    <div class="panel__head"><h2 id="tr"><?= e(ClinicalReferenceService::TRIAGE_OUTCOMES[$o]) ?></h2></div>
    <ul><?php foreach ($result['reasons'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
    <?php if ($o === 'emergency'): ?>
      <p><strong>Call 112</strong> or go to the nearest emergency department now.</p>
    <?php elseif ($o === 'urgent'): ?>
      <p>Contact a doctor today. If you cannot, <a href="<?= e(url('/patient/appointments/new', ['kind' => 'teleconsultation'])) ?>">book a teleconsultation</a>. Call 112 if you get worse.</p>
    <?php elseif ($o === 'test'): ?>
      <div class="cluster"><a class="btn btn--primary" href="<?= e(url('/patient/requests/new')) ?>">Request a test</a><a class="btn btn--secondary" href="<?= e(url('/patient/appointments/new', ['kind' => 'consultation'])) ?>">Book a consultation</a></div>
    <?php else: ?>
      <p>Rest, drink fluids and watch for changes. Answer again if anything gets worse.</p>
    <?php endif; ?>
    <p class="disclaimer">This is not a diagnosis. If you are worried, speak to a doctor whatever this says.</p>
  </section>
<?php endif; ?>
<form method="post" action="<?= e(url('/patient/triage')) ?>" class="mt-4">
  <?= csrf_field() ?>
  <section class="panel">
    <fieldset class="mb-0"><legend>1. Is any of this happening now?</legend>
      <div class="choice-grid"><?php foreach ($flags as $k => $l): ?><label class="choice"><input type="checkbox" name="red_flags[]" value="<?= e($k) ?>"<?= checked(in_array($k, $chosen('red_flags'), true)) ?>><span class="choice__text"><?= e($l) ?></span></label><?php endforeach; ?></div>
    </fieldset>
  </section>
  <section class="panel">
    <fieldset class="mb-0"><legend>2. Symptoms</legend>
      <div class="choice-grid"><?php foreach ($symptoms as $k => $l): ?><label class="choice"><input type="checkbox" name="symptoms[]" value="<?= e($k) ?>"<?= checked(in_array($k, $chosen('symptoms'), true)) ?>><span class="choice__text"><?= e($l) ?></span></label><?php endforeach; ?></div>
      <div class="form-grid form-grid--2 mt-4">
        <div class="field"><label for="f-days">For how many days?</label><select class="input" id="f-days" name="days"><?php foreach ([0 => 'Less than a day', 1 => '1–2 days', 3 => '3–6 days', 7 => 'A week or more'] as $k => $l): ?><option value="<?= $k ?>"<?= selected((string) ($old['days'] ?? '0'), (string) $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <label class="checkbox-line"><input type="checkbox" name="worse" value="1"<?= checked(($old['worse'] ?? '') === '1') ?>> <span>Getting worse</span></label>
      </div>
    </fieldset>
  </section>
  <section class="panel">
    <fieldset class="mb-0"><legend>3. Does any of this apply?</legend>
      <div class="choice-grid"><?php foreach ($risks as $k => $l): ?><label class="choice"><input type="checkbox" name="risks[]" value="<?= e($k) ?>"<?= checked(in_array($k, $chosen('risks'), true)) ?>><span class="choice__text"><?= e($l) ?></span></label><?php endforeach; ?></div>
    </fieldset>
    <div class="form-actions"><button class="btn btn--primary" type="submit">See what to do next</button></div>
  </section>
</form>
