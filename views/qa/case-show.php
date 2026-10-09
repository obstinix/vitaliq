<?php
use Vitaliq\Support\Labels;

$c = $case;
$runTypes = ['regex_suite' => 'Regex suite', 'manual' => 'Manual', 'phpunit' => 'PHPUnit'];
?>
<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/qa/cases')) ?>">Test case catalogue</a></p>
    <h1><span class="mono"><?= e($c['case_code']) ?></span></h1>
    <p><?= e($c['title']) ?></p>
  </div>
  <?php if ($c['kind'] === 'regex'): ?>
    <a class="btn btn--secondary" href="<?= e(url('/qa/validator', ['rule' => $c['rule_key']])) ?>">Open rule tester</a>
  <?php endif; ?>
</div>

<div class="grid-2">
  <div class="stack">
    <section class="panel" aria-labelledby="def-title">
      <div class="panel__head"><h2 id="def-title">Definition</h2><span class="tag"><?= $c['origin'] === 'catalogue' ? 'Catalogue case' : 'Added in the lab' ?></span></div>
      <dl class="details">
        <dt>Module</dt><dd><?= e($c['module']) ?></dd>
        <dt>Kind</dt><dd><?= $c['kind'] === 'regex' ? 'Regex (executed automatically)' : 'Manual' ?></dd>
        <dt>Case type</dt><dd><?= e(Labels::get(Labels::CASE_TYPES, $c['case_type'])) ?></dd>
        <?php if ($c['rule_key']): ?><dt>Rule</dt><dd class="mono"><?= e($c['rule_key']) ?></dd><?php endif; ?>
        <?php if ($c['preconditions']): ?><dt>Preconditions</dt><dd><?= e($c['preconditions']) ?></dd><?php endif; ?>
        <dt><?= $c['kind'] === 'regex' ? 'Input' : 'Steps / input' ?></dt><dd><span class="input-echo"><?= e($c['kind'] === 'regex' ? show_input($c['input_value']) : $c['input_value']) ?></span></dd>
        <dt>Expected</dt><dd><?= e($c['expected_result']) ?></dd>
        <?php if ($c['notes']): ?><dt>Notes</dt><dd><?= e($c['notes']) ?></dd><?php endif; ?>
        <dt>Defect</dt><dd><?= $c['defect_code'] ? '<a href="' . e(url('/qa/defects/' . (int) $c['defect_id'])) . '">' . e($c['defect_code'] . ' — ' . $c['defect_title']) . '</a>' : 'None linked' ?></dd>
      </dl>
    </section>

    <section class="panel" aria-labelledby="exec-title">
      <div class="panel__head"><h2 id="exec-title">Execution history</h2></div>
      <?php if ($c['executions'] === []): ?>
        <p class="muted mb-0">This case has not been executed yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table table--stack">
            <thead><tr><th scope="col">When</th><th scope="col">Run</th><th scope="col">Status</th><th scope="col">Actual result</th><th scope="col">By</th></tr></thead>
            <tbody>
              <?php foreach ($c['executions'] as $x): ?>
                <tr>
                  <td data-label="When" class="nowrap small"><?= e(fmt_datetime($x['executed_at'])) ?></td>
                  <td data-label="Run"><a href="<?= e(url('/qa/runs/' . (int) $x['run_id'])) ?>">#<?= (int) $x['run_id'] ?></a> <span class="small muted"><?= e($runTypes[$x['run_type']] ?? $x['run_type']) ?></span></td>
                  <td data-label="Status"><span class="status status--<?= e($x['status']) ?>"><?= e(ucfirst($x['status'])) ?></span></td>
                  <td data-label="Actual" class="small"><?= e($x['actual_result']) ?></td>
                  <td data-label="By" class="small"><?= e($x['executed_by'] ?? ($x['triggered_from'] === 'cli' ? 'CLI' : '—')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <section class="panel" aria-labelledby="record-title">
      <div class="panel__head"><h2 id="record-title">Record an execution</h2></div>
      <?php if ($c['kind'] === 'regex'): ?>
        <p class="mb-4">Regex cases are executed by the suite, which compares the validator’s actual output with the expectation.</p>
        <form method="post" action="<?= e(url('/qa/runs')) ?>" data-once>
          <?= csrf_field() ?>
          <button class="btn btn--primary btn--small" type="submit">Run the regex suite</button>
        </form>
      <?php else: ?>
        <?= $view->partial('error-summary', ['errors' => $errors]) ?>
        <form class="stack" method="post" action="<?= e(url('/qa/cases/' . (int) $c['id'] . '/execute')) ?>" novalidate data-once>
          <?= csrf_field() ?>
          <div class="field">
            <label for="f-status">Outcome</label>
            <select class="input" id="f-status" name="status" required<?= aria_invalid($errors, 'status') ?>>
              <option value="">Choose</option>
              <option value="pass"<?= selected(old($old, 'status'), 'pass') ?>>Pass: actual matched expected</option>
              <option value="fail"<?= selected(old($old, 'status'), 'fail') ?>>Fail: actual differed</option>
              <option value="blocked"<?= selected(old($old, 'status'), 'blocked') ?>>Blocked: could not execute</option>
            </select>
            <?= field_error($errors, 'status') ?>
          </div>
          <div class="field">
            <label for="f-actual_result">What actually happened</label>
            <textarea class="input" id="f-actual_result" name="actual_result" maxlength="500" rows="3" required<?= aria_invalid($errors, 'actual_result') ?>><?= e(old($old, 'actual_result')) ?></textarea>
            <?= field_error($errors, 'actual_result') ?>
          </div>
          <button class="btn btn--primary btn--small" type="submit">Save execution</button>
        </form>
      <?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="link-title">
      <div class="panel__head"><h2 id="link-title">Linked defect</h2><a href="<?= e(url('/qa/defects/new')) ?>">Report new</a></div>
      <form class="stack" method="post" action="<?= e(url('/qa/cases/' . (int) $c['id'] . '/defect')) ?>">
        <?= csrf_field() ?>
        <label class="visually-hidden" for="f-defect_id">Defect</label>
        <select class="input" id="f-defect_id" name="defect_id">
          <option value="0">No defect</option>
          <?php foreach ($defects as $id => $label): ?><option value="<?= (int) $id ?>"<?= selected((string) ($c['defect_id'] ?? ''), (string) $id) ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn--secondary btn--small" type="submit">Save link</button>
      </form>
    </section>
  </div>
</div>
