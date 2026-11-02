<?php
$sev = ['contraindicated' => ['Do not combine', 'critical'], 'major' => ['Major', 'critical'], 'moderate' => ['Moderate', 'warning']];
?>
<div class="app-head">
  <div>
    <h1><?= e(t('Medicine checker')) ?></h1>
    <p>Check a few well-known interactions between medicines. The list is short; a pair it does not know may still interact.</p>
  </div>
</div>
<div class="vq-grid">
  <section class="panel span-5" aria-labelledby="mq">
    <div class="panel__head"><h2 id="mq">Your medicines</h2></div>
    <form method="get" action="<?= e(url('/patient/medicines')) ?>" novalidate>
      <div class="field"><label for="f-medicines">One medicine per line (2 to 8)</label>
        <textarea class="input" id="f-medicines" name="m" rows="6" maxlength="600"<?= aria_invalid($errors, 'medicines') ?>><?= e($m) ?></textarea><?= field_error($errors, 'medicines') ?>
        <p class="hint">Use the medicine's name, such as warfarin or ibuprofen; the strength is ignored.</p></div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Check</button></div>
    </form>
  </section>
  <section class="panel span-7" aria-labelledby="mr">
    <div class="panel__head"><h2 id="mr">Result</h2></div>
    <?php if ($result === null): ?>
      <p class="empty">Enter your medicines to check them.</p>
    <?php else: ?>
      <?php if ($result['found'] === []): ?>
        <p class="callout callout--note">No interaction found in this limited list for <?= e(implode(', ', $result['medicines'])) ?>. That is not proof the combination is safe.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($result['found'] as $f): ?>
            <li><span class="grow"><strong><?= e(ucwords($f['pair'])) ?></strong><span class="sub"><?= e($f['effect']) ?></span><span class="sub"><?= e($f['advice']) ?></span></span>
              <span class="status status--<?= e($sev[$f['severity']][1]) ?>"><?= e($sev[$f['severity']][0]) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($result['unknown'] !== []): ?><p class="hint mt-4">Not in this list at all: <?= e(implode(', ', $result['unknown'])) ?>.</p><?php endif; ?>
    <?php endif; ?>
    <p class="disclaimer">Reference only. Do not start, stop or change a medicine without asking your doctor or pharmacist.</p>
  </section>
</div>
