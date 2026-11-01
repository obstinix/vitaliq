<?php
use Vitaliq\Services\LaboratoryService;

$fmt = static fn ($v, int $d): string => $v === null ? '' : number_format((float) $v, $d);
?>
<div class="app-head">
  <div>
    <h1>Test catalogue</h1>
    <p><?= count($tests) ?> tests. Reference ranges are editable starting values; verify them for your own analysers and population before reporting.</p>
  </div>
</div>
<div class="table-wrap">
  <table class="table table--stack">
    <thead><tr><th scope="col">Test</th><th scope="col">Section</th><th scope="col">Specimen</th><th scope="col">Reference range</th><th scope="col">Critical limits</th><th scope="col" class="num">Target</th></tr></thead>
    <tbody>
      <?php foreach ($tests as $t): ?>
        <tr>
          <td data-label="Test"><a href="<?= e(url('/lab/catalogue/' . (int) $t['id'])) ?>"><?= e($t['name']) ?></a><span class="sub"><?= e($t['code']) ?><?= $t['unit'] ? ' · ' . e($t['unit']) : '' ?></span></td>
          <td data-label="Section"><?= e(LaboratoryService::SECTIONS[$t['section']]) ?></td>
          <td data-label="Specimen"><?= e(LaboratoryService::SPECIMENS[$t['specimen']]) ?></td>
          <td data-label="Reference range">
            <?php if ($t['result_type'] === 'qualitative'): ?>
              <?= e(str_replace('|', ' / ', (string) $t['options'])) ?>
            <?php elseif (($ranges[(int) $t['id']] ?? []) === []): ?>
              <span class="faint">—</span>
            <?php else: foreach ($ranges[(int) $t['id']] as $r): ?>
              <span class="sub"><?= e(ucfirst($r['sex'])) ?>: <?= e($fmt($r['low'], (int) $t['decimals'])) ?> – <?= e($fmt($r['high'], (int) $t['decimals'])) ?></span>
            <?php endforeach; endif; ?>
          </td>
          <td data-label="Critical limits">
            <?php foreach ($ranges[(int) $t['id']] ?? [] as $r): ?>
              <?php if ($r['critical_low'] !== null || $r['critical_high'] !== null): ?><span class="sub">&lt; <?= e($fmt($r['critical_low'], (int) $t['decimals']) ?: '—') ?> · &gt; <?= e($fmt($r['critical_high'], (int) $t['decimals']) ?: '—') ?></span><?php endif; ?>
            <?php endforeach; ?>
          </td>
          <td data-label="Target" class="num"><?= (int) $t['turnaround_hours'] ?> h</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
