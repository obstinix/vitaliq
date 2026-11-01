<?php
use Vitaliq\Services\LaboratoryService;

$theirs = array_values(array_filter($rows, static fn ($r) => (int) $r['mine'] === 0));
?>
<div class="app-head">
  <div>
    <h1>Awaiting verification</h1>
    <p><?= count($theirs) ?> <?= count($theirs) === 1 ? 'result' : 'results' ?> you can verify<?= count($rows) > count($theirs) ? ' · ' . (count($rows) - count($theirs)) . ' you entered wait for a colleague' : '' ?>. Critical results are first.</p>
  </div>
</div>
<?php if ($rows === []): ?>
  <p class="empty">Nothing is waiting for a second check.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table--stack">
      <thead><tr><th scope="col">Test</th><th scope="col">Patient</th><th scope="col" class="num">Result</th><th scope="col">Flag</th><th scope="col">Entered</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td data-label="Test"><?= e($r['name']) ?><span class="sub"><a href="<?= e(url('/lab/orders/' . (int) $r['order_id'])) ?>"><?= e($r['order_no']) ?></a><?= $r['priority'] !== 'routine' ? ' · ' . e(LaboratoryService::PRIORITIES[$r['priority']]) : '' ?></span></td>
            <td data-label="Patient"><?= e($r['full_name']) ?></td>
            <td data-label="Result" class="num"><?= e($r['value_text'] ?? rtrim(rtrim((string) $r['value_numeric'], '0'), '.')) ?> <span class="faint"><?= e($r['unit'] ?? '') ?></span></td>
            <td data-label="Flag"><span class="flag flag--<?= e(str_starts_with((string) $r['flag'], 'critical') ? 'critical' : (string) $r['flag']) ?>"><?= e(LaboratoryService::FLAGS[$r['flag']] ?? '') ?></span></td>
            <td data-label="Entered"><?= e($r['entered_by_name'] ?? '—') ?><span class="sub"><?= e(fmt_datetime($r['entered_at'])) ?></span></td>
            <td data-label="Actions">
              <?php if ((int) $r['mine'] === 0): ?>
                <form method="post" action="<?= e(url('/lab/items/' . (int) $r['id'] . '/verify')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="queue"><button class="btn btn--primary btn--small" type="submit">Verify</button></form>
              <?php else: ?><span class="hint">Entered by you</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
