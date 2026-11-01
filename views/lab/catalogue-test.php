<?php
use Vitaliq\Services\LaboratoryService;

$d = (int) $test['decimals'];
$fmt = static fn ($v): string => $v === null ? '' : number_format((float) $v, $d, '.', '');
?>
<div class="app-head">
  <div>
    <h1><?= e($test['name']) ?></h1>
    <p><?= e($test['code']) ?> · <?= e(LaboratoryService::SECTIONS[$test['section']]) ?> · <?= e(LaboratoryService::SPECIMENS[$test['specimen']]) ?><?= $test['unit'] ? ' · ' . e($test['unit']) : '' ?> · target <?= (int) $test['turnaround_hours'] ?> h</p>
  </div>
  <a href="<?= e(url('/lab/catalogue')) ?>">Catalogue</a>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<?php if ($test['result_type'] === 'qualitative'): ?>
  <section class="panel"><p>Qualitative test. Choices: <?= e(str_replace('|', ', ', (string) $test['options'])) ?>. Flagged abnormal: <?= e(str_replace('|', ', ', (string) $test['abnormal_options'])) ?>.</p></section>
<?php else: ?>
  <?php foreach (array_merge($ranges, [null]) as $r): ?>
    <section class="panel">
      <div class="panel__head"><h2><?= $r === null ? 'Add a range' : e(ucfirst($r['sex'])) . ', ' . (int) $r['age_min_years'] . '–' . (int) $r['age_max_years'] . ' years' ?></h2>
        <?php if ($r !== null): ?><span class="meta">Updated <?= e(fmt_datetime($r['updated_at'])) ?></span><?php endif; ?></div>
      <form method="post" action="<?= e(url('/lab/catalogue/' . (int) $test['id'] . '/ranges')) ?>" novalidate>
        <?= csrf_field() ?><input type="hidden" name="range_id" value="<?= (int) ($r['id'] ?? 0) ?>">
        <div class="form-grid form-grid--3">
          <div class="field"><label for="sx-<?= (int) ($r['id'] ?? 0) ?>">Applies to</label><select class="input" id="sx-<?= (int) ($r['id'] ?? 0) ?>" name="sex"><?php foreach (['any' => 'Everyone', 'female' => 'Female', 'male' => 'Male'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected((string) ($r['sex'] ?? 'any'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="a1-<?= (int) ($r['id'] ?? 0) ?>">From age</label><input class="input" id="a1-<?= (int) ($r['id'] ?? 0) ?>" name="age_min_years" inputmode="numeric" value="<?= (int) ($r['age_min_years'] ?? 0) ?>"></div>
          <div class="field"><label for="a2-<?= (int) ($r['id'] ?? 0) ?>">To age</label><input class="input" id="a2-<?= (int) ($r['id'] ?? 0) ?>" name="age_max_years" inputmode="numeric" value="<?= (int) ($r['age_max_years'] ?? 120) ?>"></div>
          <?php foreach (['critical_low' => 'Critical low', 'low' => 'Lower limit', 'high' => 'Upper limit', 'critical_high' => 'Critical high'] as $k => $l): ?>
            <div class="field"><label for="<?= e($k) ?>-<?= (int) ($r['id'] ?? 0) ?>"><?= e($l) ?></label><input class="input input--mono" id="<?= e($k) ?>-<?= (int) ($r['id'] ?? 0) ?>" name="<?= e($k) ?>" inputmode="decimal" value="<?= e($fmt($r[$k] ?? null)) ?>"></div>
          <?php endforeach; ?>
        </div>
        <div class="form-actions"><button class="btn btn--secondary" type="submit"><?= $r === null ? 'Add range' : 'Save range' ?></button></div>
      </form>
    </section>
  <?php endforeach; ?>
  <p class="disclaimer">Changing a range affects results entered from now on. Results already entered keep the range they were checked against.</p>
<?php endif; ?>
