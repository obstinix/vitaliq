<?php use Vitaliq\Support\Labels; ?>
<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/qa/defects')) ?>">Defect register</a></p>
    <h1>Report a defect</h1>
    <p>Describe what you observed. A good report lets someone else reproduce it without asking questions.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<form class="form" method="post" action="<?= e(url('/qa/defects/new')) ?>" novalidate data-once>
  <?= csrf_field() ?>
  <div class="form-grid form-grid--3">
    <div class="field span-all">
      <label for="f-title">Title</label>
      <input class="input" id="f-title" name="title" maxlength="160" required value="<?= e(old($old, 'title')) ?>"<?= aria_invalid($errors, 'title') ?>>
      <?= field_error($errors, 'title') ?>
    </div>
    <div class="field">
      <label for="f-module">Module</label>
      <input class="input" id="f-module" name="module" maxlength="40" required value="<?= e(old($old, 'module')) ?>"<?= aria_invalid($errors, 'module') ?>>
      <?= field_error($errors, 'module') ?>
    </div>
    <div class="field">
      <label for="f-severity">Severity</label>
      <select class="input" id="f-severity" name="severity"<?= aria_invalid($errors, 'severity') ?>><?php foreach (Labels::SEVERITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'severity', 'minor'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    </div>
    <div class="field">
      <label for="f-priority">Priority</label>
      <select class="input" id="f-priority" name="priority"<?= aria_invalid($errors, 'priority') ?>><?php foreach (Labels::PRIORITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'priority', 'medium'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    </div>
    <?php foreach ([
        'description' => ['Description', 4000, 3],
        'steps_to_reproduce' => ['Steps to reproduce', 4000, 4],
        'expected_behaviour' => ['Expected behaviour', 2000, 2],
        'actual_behaviour' => ['Actual behaviour', 2000, 2],
    ] as $field => [$label, $max, $rows]): ?>
      <div class="field span-all">
        <label for="f-<?= e($field) ?>"><?= e($label) ?></label>
        <textarea class="input" id="f-<?= e($field) ?>" name="<?= e($field) ?>" maxlength="<?= (int) $max ?>" rows="<?= (int) $rows ?>" required<?= aria_invalid($errors, $field) ?>><?= e(old($old, $field)) ?></textarea>
        <?= field_error($errors, $field) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="form-actions">
    <button class="btn btn--primary" type="submit">Record defect</button>
    <a href="<?= e(url('/qa/defects')) ?>">Cancel</a>
  </div>
</form>
