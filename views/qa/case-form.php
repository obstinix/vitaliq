<?php use Vitaliq\Support\Labels; ?>
<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/qa/cases')) ?>">Test case catalogue</a></p>
    <h1>New test case</h1>
    <p>Write the expected result now. The actual result is recorded only when the case is executed.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<form class="form" method="post" action="<?= e(url('/qa/cases/new')) ?>" novalidate data-once>
  <?= csrf_field() ?>
  <fieldset>
    <legend><span class="num">01</span>Case</legend>
    <div class="form-grid form-grid--2">
      <div class="field">
        <label for="f-kind">Kind</label>
        <select class="input" id="f-kind" name="kind" data-kind-select<?= aria_invalid($errors, 'kind') ?>>
          <option value="regex"<?= selected(old($old, 'kind'), 'regex') ?>>Regex: executed automatically</option>
          <option value="manual"<?= selected(old($old, 'kind'), 'manual') ?>>Manual: executed by a person</option>
        </select>
      </div>
      <div class="field">
        <label for="f-case_type">Case type</label>
        <select class="input" id="f-case_type" name="case_type"<?= aria_invalid($errors, 'case_type') ?>>
          <?php foreach (Labels::CASE_TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'case_type'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
        <?= field_error($errors, 'case_type') ?>
      </div>
      <div class="field">
        <label for="f-module">Module</label>
        <input class="input" id="f-module" name="module" maxlength="40" list="module-list" value="<?= e(old($old, 'module')) ?>"<?= aria_invalid($errors, 'module') ?>>
        <datalist id="module-list"><?php foreach ($modules as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?></datalist>
        <?= field_error($errors, 'module') ?>
      </div>
      <div class="field">
        <label for="f-title">Title</label>
        <input class="input" id="f-title" name="title" maxlength="200" value="<?= e(old($old, 'title')) ?>"<?= aria_invalid($errors, 'title') ?>>
        <?= field_error($errors, 'title') ?>
      </div>
      <div class="field span-2">
        <label for="f-preconditions">Preconditions <span class="optional">(optional)</span></label>
        <input class="input" id="f-preconditions" name="preconditions" maxlength="300" value="<?= e(old($old, 'preconditions')) ?>">
      </div>
    </div>
  </fieldset>
  <fieldset>
    <legend><span class="num">02</span>Input and expectation</legend>
    <div class="form-grid form-grid--2">
      <div class="field" data-show-when="kind-regex">
        <label for="f-rule_key">Rule</label>
        <select class="input" id="f-rule_key" name="rule_key"<?= aria_invalid($errors, 'rule_key') ?>>
          <?php foreach ($rules as $k => $r): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'rule_key'), $k) ?>><?= e($r['label']) ?></option><?php endforeach; ?>
        </select>
        <?= field_error($errors, 'rule_key') ?>
      </div>
      <div class="field" data-show-when="kind-regex">
        <label for="f-expected_match">Expected outcome</label>
        <select class="input" id="f-expected_match" name="expected_match"<?= aria_invalid($errors, 'expected_match') ?>>
          <option value="1"<?= selected(old($old, 'expected_match'), '1') ?>>Accepted by the validator</option>
          <option value="0"<?= selected(old($old, 'expected_match'), '0') ?>>Rejected by the validator</option>
        </select>
        <?= field_error($errors, 'expected_match') ?>
      </div>
      <div class="field span-2">
        <label for="f-input_value">Input value, or steps for manual cases</label>
        <textarea class="input input--mono" id="f-input_value" name="input_value" maxlength="500" rows="3"<?= aria_invalid($errors, 'input_value') ?>><?= e(old($old, 'input_value')) ?></textarea>
        <p class="hint">For regex cases the value is used exactly as typed, including leading and trailing spaces.</p>
        <?= field_error($errors, 'input_value') ?>
      </div>
      <div class="field span-2" data-show-when="kind-manual">
        <label for="f-expected_result">Expected result</label>
        <input class="input" id="f-expected_result" name="expected_result" maxlength="300" value="<?= e(old($old, 'expected_result')) ?>"<?= aria_invalid($errors, 'expected_result') ?>>
        <?= field_error($errors, 'expected_result') ?>
      </div>
      <div class="field span-2">
        <label for="f-notes">Notes <span class="optional">(optional)</span></label>
        <input class="input" id="f-notes" name="notes" maxlength="500" value="<?= e(old($old, 'notes')) ?>">
      </div>
    </div>
  </fieldset>
  <div class="form-actions">
    <button class="btn btn--primary" type="submit">Add to catalogue</button>
    <a href="<?= e(url('/qa/cases')) ?>">Cancel</a>
  </div>
</form>
