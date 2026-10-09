<?php use Vitaliq\Support\Labels; ?>
<form class="stack" method="post" action="<?= e($action) ?>" novalidate data-validate data-once>
  <?= csrf_field() ?>
  <div class="field">
    <label for="f-specimen">Specimen</label>
    <select class="input" id="f-specimen" name="specimen" required<?= aria_invalid($errors, 'specimen') ?>>
      <option value="">Choose</option>
      <?php foreach (Labels::SPECIMENS as $key => $label): ?>
        <option value="<?= e($key) ?>"<?= selected(old($old, 'specimen'), $key) ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?= field_error($errors, 'specimen') ?>
  </div>
  <div class="field">
    <label for="f-barcode">Tube barcode</label>
    <input class="input input--mono" id="f-barcode" name="barcode" maxlength="14" required autocomplete="off" spellcheck="false" data-rule="sample_barcode" value="<?= e(old($old, 'barcode')) ?>"<?= aria_invalid($errors, 'barcode') ?>>
    <p class="hint">8–14 letters or digits, as printed on the label.</p>
    <?= field_error($errors, 'barcode') ?>
  </div>
  <div class="field">
    <label for="f-note">Note <span class="optional">(optional)</span></label>
    <input class="input" id="f-note" name="note" maxlength="300" value="<?= e(old($old, 'note')) ?>">
  </div>
  <button class="btn btn--primary" type="submit" data-busy-label="Saving…">Confirm sample collected</button>
</form>
