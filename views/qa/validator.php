<?php
use Vitaliq\Support\Labels;

$current = $rules[$rule];
?>
<div class="app-head">
  <div>
    <p class="eyebrow">STQA laboratory</p>
    <h1>Validation rule tester</h1>
    <p>Run any value through the same server-side rules that protect Vitaliq’s forms. Patterns are fixed in code; you choose a rule, not a regex.</p>
  </div>
</div>

<div class="grid-2">
  <div class="stack">
    <section class="panel" aria-labelledby="try-title">
      <div class="panel__head"><h2 id="try-title">Try a value</h2></div>
      <?= $view->partial('error-summary', ['errors' => $errors]) ?>
      <form class="stack" method="post" action="<?= e(url('/qa/validator')) ?>" data-qa-form novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="qa-rule">Rule</label>
          <select class="input" id="qa-rule" name="rule" data-qa-rule>
            <?php foreach ($rules as $key => $r): ?>
              <option value="<?= e($key) ?>"<?= selected($rule, $key) ?>><?= e($r['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="qa-value">Test value</label>
          <textarea class="input input--mono" id="qa-value" name="value" rows="2" maxlength="500" spellcheck="false" autocomplete="off" data-qa-value aria-describedby="qa-format"><?= e($value) ?></textarea>
          <p class="hint" id="qa-format" data-qa-format>Expected: <?= e($current['format']) ?></p>
        </div>
        <div class="cluster">
          <button class="btn btn--primary" type="submit">Evaluate on the server</button>
          <span class="hint">Results also update as you type.</span>
        </div>
      </form>

      <div class="verdict mt-5" data-qa-verdict aria-live="polite"<?= $result !== null ? ' data-state="' . ($result['accepted'] ? 'accepted' : 'rejected') . '"' : '' ?>>
        <?php if ($result === null): ?>
          <p class="verdict__head">Waiting for input</p>
          <p class="mb-0 muted">Enter a value to see whether the validator accepts it.</p>
        <?php else: ?>
          <p class="verdict__head"><?= $result['accepted'] ? 'Accepted' : 'Rejected' ?></p>
          <dl class="details">
            <dt>Input</dt><dd><span class="input-echo"><?= e($value) ?></span> (<?= (int) $result['input_length'] ?> characters)</dd>
            <dt>After normalising</dt><dd><span class="input-echo"><?= e($result['normalised']) ?></span></dd>
            <dt>Pattern match</dt><dd><?= $result['regex_match'] ? 'Yes' : 'No' ?></dd>
            <dt>Message</dt><dd><?= e($result['message'] ?? 'None') ?></dd>
            <dt>Server time</dt><dd><?= e($result['duration_ms']) ?> ms</dd>
          </dl>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="stack">
    <section class="panel" aria-labelledby="rule-title">
      <div class="panel__head"><h2 id="rule-title" data-qa-label><?= e($current['label']) ?></h2></div>
      <p data-qa-description><?= e($current['description']) ?></p>
      <p class="field-label">Pattern</p>
      <code class="pattern" data-qa-pattern><?= e($current['pattern']) ?></code>
      <p class="hint mt-4">Compiled as <code>/…/u</code> in PHP and <code>new RegExp(…, "u")</code> in the browser. Length limits: <span data-qa-limits><?= (int) $current['min'] ?>–<?= (int) $current['max'] ?></span> characters.</p>
    </section>

    <section class="panel" aria-labelledby="cases-title">
      <div class="panel__head"><h2 id="cases-title">Catalogue cases for this rule</h2><a href="<?= e(url('/qa/cases', ['kind' => 'regex'])) ?>">All</a></div>
      <?php foreach ($rules as $key => $r): ?>
        <div data-qa-cases="<?= e($key) ?>"<?= $key === $rule ? '' : ' hidden' ?>>
          <?php $cases = $casesByRule[$key] ?? []; ?>
          <?php if ($cases === []): ?>
            <p class="muted mb-0">No catalogue cases for this rule.</p>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table">
                <thead><tr><th scope="col">Case</th><th scope="col">Input</th><th scope="col">Expect</th><th scope="col">Last run</th></tr></thead>
                <tbody>
                  <?php foreach ($cases as $c): ?>
                    <tr>
                      <td><a class="mono small" href="<?= e(url('/qa/cases/' . (int) $c['id'])) ?>"><?= e($c['case_code']) ?></a><br><span class="tag"><?= e(Labels::get(Labels::CASE_TYPES, $c['case_type'])) ?></span></td>
                      <td><button class="link-button mono small" type="button" data-qa-use="<?= e($c['input_value']) ?>"><?= e(show_input($c['input_value'])) ?></button></td>
                      <td><?= (int) $c['expected_match'] === 1 ? 'Accept' : 'Reject' ?></td>
                      <td><?php if ($c['last_status']): ?><span class="status status--<?= e($c['last_status']) ?>"><?= e(ucfirst($c['last_status'])) ?></span><?php else: ?><span class="status status--not_run">Not run</span><?php endif; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>
  </div>
</div>
<?= json_script('qa-rule-meta', array_map(static fn ($r) => ['label' => $r['label'], 'description' => $r['description'], 'format' => $r['format'], 'pattern' => $r['pattern'], 'min' => $r['min'], 'max' => $r['max']], $rules)) ?>
