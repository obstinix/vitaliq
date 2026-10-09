<?php
/**
 * Test details fieldsets shared by the guest and patient request forms.
 * Expects $old, $errors, $states, $today, $maxDate, $n (first section number).
 */
use Vitaliq\Support\Labels;

$n = $n ?? 1;
$symptomsOld = isset($old['symptoms']) && is_array($old['symptoms']) ? $old['symptoms'] : [];
?>
<fieldset>
  <legend><span class="num"><?= sprintf('%02d', $n) ?></span>The test</legend>
  <div class="field" id="f-test_type">
    <span class="field-label" id="lbl-test_type">Test type</span>
    <div class="choice-grid" role="radiogroup" aria-labelledby="lbl-test_type"<?= aria_invalid($errors, 'test_type') ?>>
      <?php foreach (Labels::TEST_TYPES as $key => $label): ?>
        <label class="choice">
          <input type="radio" name="test_type" value="<?= e($key) ?>" required<?= checked(old($old, 'test_type') === $key) ?>>
          <span class="choice__text"><?= e($label) ?>
            <small><?= $key === 'rt_pcr' ? 'Most sensitive · laboratory result' : 'Faster · may need repeating if negative' ?></small>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <?= field_error($errors, 'test_type') ?>
  </div>

  <div class="form-grid form-grid--2 mt-5">
    <div class="field">
      <label for="f-reason">Main reason for testing</label>
      <select class="input" id="f-reason" name="reason" required<?= aria_invalid($errors, 'reason') ?>>
        <option value="">Choose a reason</option>
        <?php foreach (Labels::REASONS as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= selected(old($old, 'reason'), $key) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'reason') ?>
    </div>
    <div class="field">
      <label for="f-exposure">Close contact with a confirmed case in the last 10 days?</label>
      <select class="input" id="f-exposure" name="exposure" required<?= aria_invalid($errors, 'exposure') ?>>
        <option value="">Choose an answer</option>
        <?php foreach (Labels::EXPOSURE as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= selected(old($old, 'exposure'), $key) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'exposure') ?>
    </div>
  </div>

  <div class="field mt-5" id="f-symptoms">
    <span class="field-label" id="lbl-symptoms">Symptoms <span class="optional">(select any you have now)</span></span>
    <div class="choice-grid" role="group" aria-labelledby="lbl-symptoms"<?= aria_invalid($errors, 'symptoms') ?>>
      <?php foreach (Labels::SYMPTOMS as $key => $label): ?>
        <label class="choice">
          <input type="checkbox" name="symptoms[]" value="<?= e($key) ?>" data-symptom<?= checked(in_array($key, $symptomsOld, true)) ?>>
          <span class="choice__text"><?= e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <?= field_error($errors, 'symptoms') ?>
  </div>

  <div class="field mt-5" data-show-when="symptoms">
    <label for="f-symptom_onset">When did symptoms start?</label>
    <input class="input" id="f-symptom_onset" name="symptom_onset" type="date" max="<?= e($today) ?>" data-rule="iso_date" value="<?= e(old($old, 'symptom_onset')) ?>"<?= aria_invalid($errors, 'symptom_onset') ?>>
    <?= field_error($errors, 'symptom_onset') ?>
  </div>
</fieldset>

<fieldset>
  <legend><span class="num"><?= sprintf('%02d', $n + 1) ?></span>Sample collection</legend>
  <div class="field" id="f-collection_mode">
    <span class="field-label" id="lbl-collection_mode">How should we collect the sample?</span>
    <div class="choice-grid" role="radiogroup" aria-labelledby="lbl-collection_mode"<?= aria_invalid($errors, 'collection_mode') ?>>
      <?php foreach (Labels::COLLECTION_MODES as $key => $label): ?>
        <label class="choice">
          <input type="radio" name="collection_mode" value="<?= e($key) ?>" required data-collection-mode<?= checked(old($old, 'collection_mode') === $key) ?>>
          <span class="choice__text"><?= e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <?= field_error($errors, 'collection_mode') ?>
  </div>

  <div class="field mt-5" data-show-when="home">
    <label for="f-collection_address">Collection address</label>
    <textarea class="input" id="f-collection_address" name="collection_address" rows="2" maxlength="200" autocomplete="street-address"<?= aria_invalid($errors, 'collection_address') ?>><?= e(old($old, 'collection_address')) ?></textarea>
    <?= field_error($errors, 'collection_address') ?>
  </div>

  <div class="form-grid form-grid--3 mt-5">
    <div class="field">
      <label for="f-city">City or town</label>
      <input class="input" id="f-city" name="city" maxlength="80" required autocomplete="address-level2" value="<?= e(old($old, 'city')) ?>"<?= aria_invalid($errors, 'city') ?>>
      <?= field_error($errors, 'city') ?>
    </div>
    <div class="field">
      <label for="f-state_code">State or union territory</label>
      <select class="input" id="f-state_code" name="state_code" required autocomplete="address-level1"<?= aria_invalid($errors, 'state_code') ?>>
        <option value="">Choose</option>
        <?php foreach ($states as $code => $name): ?>
          <option value="<?= e($code) ?>"<?= selected(old($old, 'state_code'), (string) $code) ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'state_code') ?>
    </div>
    <div class="field">
      <label for="f-pincode">PIN code</label>
      <input class="input input--mono" id="f-pincode" name="pincode" inputmode="numeric" maxlength="7" required autocomplete="postal-code" data-rule="pincode" value="<?= e(old($old, 'pincode')) ?>"<?= aria_invalid($errors, 'pincode') ?>>
      <?= field_error($errors, 'pincode') ?>
    </div>
  </div>

  <div class="form-grid form-grid--2 mt-5">
    <div class="field">
      <label for="f-preferred_date">Preferred date</label>
      <input class="input" id="f-preferred_date" name="preferred_date" type="date" min="<?= e($today) ?>" max="<?= e($maxDate) ?>" required data-rule="iso_date" value="<?= e(old($old, 'preferred_date')) ?>"<?= aria_invalid($errors, 'preferred_date') ?>>
      <p class="hint">Within the next 14 days.</p>
      <?= field_error($errors, 'preferred_date') ?>
    </div>
    <div class="field">
      <label for="f-preferred_slot">Preferred time</label>
      <select class="input" id="f-preferred_slot" name="preferred_slot" required<?= aria_invalid($errors, 'preferred_slot') ?>>
        <option value="">Choose a slot</option>
        <?php foreach (Labels::SLOTS as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= selected(old($old, 'preferred_slot'), $key) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'preferred_slot') ?>
    </div>
  </div>

  <div class="field mt-5">
    <label for="f-notes">Notes for the collection team <span class="optional">(optional)</span></label>
    <textarea class="input" id="f-notes" name="notes" rows="2" maxlength="500" aria-describedby="hint-notes"<?= aria_invalid($errors, 'notes') ?>><?= e(old($old, 'notes')) ?></textarea>
    <p class="hint" id="hint-notes">For example access instructions. Please do not include unrelated medical history.</p>
    <?= field_error($errors, 'notes') ?>
  </div>
</fieldset>

<div class="field">
  <label class="checkbox-line" for="f-consent">
    <input type="checkbox" id="f-consent" name="consent" value="1" required<?= checked(old($old, 'consent') === '1') ?><?= aria_invalid($errors, 'consent') ?>>
    <span>I confirm these details are accurate and consent to a sample being collected for COVID-19 testing. <a href="<?= e(url('/privacy')) ?>">How we use this information</a>.</span>
  </label>
  <?= field_error($errors, 'consent') ?>
</div>
