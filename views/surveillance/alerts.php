<?php
$open = count(array_filter($alerts, static fn ($a) => $a['status'] === 'open'));
$fmtRule = static function (array $r) use ($methods): string {
    $t = rtrim(rtrim((string) $r['threshold'], '0'), '.');
    return match ($r['method']) {
        'count' => $t . '+ positive in ' . (int) $r['window_days'] . ' days',
        'positivity' => $t . '%+ positive in ' . (int) $r['window_days'] . ' days (min ' . (int) $r['min_tests'] . ' tests)',
        default => 'More than ' . $t . ' SD above the previous four ' . (int) $r['window_days'] . '-day windows',
    };
};
?>
<div class="app-head">
  <div>
    <h1>Outbreak alerts</h1>
    <p><?= $open ?> open · <?= count(array_filter($rules, static fn ($r) => (int) $r['is_active'] === 1)) ?> active rules. Windows end yesterday, because today is still filling.</p>
  </div>
  <form method="post" action="<?= e(url('/surveillance/alerts/check')) ?>"><?= csrf_field() ?><button class="btn btn--primary" type="submit">Run the check now</button></form>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<nav class="tabs" aria-label="Alert status">
  <?php foreach (['' => 'All', 'open' => 'Open', 'acknowledged' => 'Acknowledged', 'closed' => 'Closed'] as $k => $l): ?>
    <a href="<?= e(url('/surveillance/alerts', ['status' => $k])) ?>"<?= $filter === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a>
  <?php endforeach; ?>
</nav>
<div class="vq-grid">
  <section class="span-8">
    <?php if ($alerts === []): ?>
      <p class="empty">No alerts in this view.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Alert</th><th scope="col" class="num">Positive / tests</th><th scope="col" class="num">Observed</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
          <tbody>
            <?php foreach ($alerts as $a): ?>
              <tr>
                <td data-label="Alert"><?= e($diseases[$a['disease']] ?? $a['disease']) ?> · <?= e($a['state_name']) ?><span class="sub"><?= e($a['window_start']) ?> to <?= e($a['window_end']) ?> · <?= e($fmtRule($a)) ?></span><?php if ($a['note']): ?><span class="sub">Note: <?= e($a['note']) ?></span><?php endif; ?></td>
                <td data-label="Positive / tests" class="num"><?= fmt_number($a['positives']) ?> / <?= fmt_number($a['tests']) ?></td>
                <td data-label="Observed" class="num"><?= e(rtrim(rtrim((string) $a['observed'], '0'), '.')) ?><?= $a['method'] === 'positivity' ? '%' : '' ?><?= $a['expected'] !== null ? '<span class="sub">expected ' . e(number_format((float) $a['expected'], 1)) . '</span>' : '' ?></td>
                <td data-label="Status"><span class="status status--<?= e($a['status'] === 'open' ? 'open' : ($a['status'] === 'acknowledged' ? 'pending' : 'closed')) ?>"><?= e(ucfirst($a['status'])) ?></span><?= $a['acknowledged_by_name'] ? '<span class="sub">' . e($a['acknowledged_by_name']) . '</span>' : '' ?></td>
                <td data-label="Actions">
                  <?php if ($a['status'] !== 'closed'): ?>
                    <details class="confirm"><summary><?= $a['status'] === 'open' ? 'Respond' : 'Close' ?></summary><div class="confirm__body">
                      <form method="post" action="<?= e(url('/surveillance/alerts/' . (int) $a['id'])) ?>">
                        <?= csrf_field() ?>
                        <div class="field"><label for="n-<?= (int) $a['id'] ?>">Note</label><input class="input" id="n-<?= (int) $a['id'] ?>" name="note" maxlength="500"></div>
                        <div class="cluster mt-4">
                          <?php if ($a['status'] === 'open'): ?><button class="btn btn--secondary btn--small" name="status" value="acknowledged" type="submit">Acknowledge</button><?php endif; ?>
                          <button class="btn btn--primary btn--small" name="status" value="closed" type="submit">Close</button>
                        </div>
                      </form>
                    </div></details>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <div class="span-4 stack">
    <section class="panel" aria-labelledby="rules">
      <div class="panel__head"><h2 id="rules">Rules</h2></div>
      <?php if ($rules === []): ?>
        <p class="empty">No rules yet.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($rules as $r): ?>
            <li><span class="grow"><?= e($diseases[$r['disease']] ?? $r['disease']) ?> · <?= e($r['state_name'] ?? 'each state') ?><span class="sub"><?= e($fmtRule($r)) ?></span></span>
              <form method="post" action="<?= e(url('/surveillance/alerts/rules/' . (int) $r['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit"><?= (int) $r['is_active'] === 1 ? 'Pause' : 'Resume' ?></button></form></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="new-rule">
      <div class="panel__head"><h2 id="new-rule">Add a rule</h2></div>
      <form method="post" action="<?= e(url('/surveillance/alerts/rules')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field"><label for="f-disease">Disease</label><select class="input" id="f-disease" name="disease"<?= aria_invalid($errors, 'disease') ?>><?php foreach ($diseases as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'disease'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="f-state_code">State</label><select class="input" id="f-state_code" name="state_code"<?= aria_invalid($errors, 'state_code') ?>><option value="">Each state separately</option><?php foreach ($states as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'state_code'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'state_code') ?></div>
          <div class="field"><label for="f-method">Rule</label><select class="input" id="f-method" name="method"<?= aria_invalid($errors, 'method') ?>><?php foreach ($methods as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'method'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'method') ?></div>
          <div class="field"><label for="f-threshold">Threshold</label><input class="input" id="f-threshold" name="threshold" inputmode="decimal" value="<?= e(old($old, 'threshold')) ?>"<?= aria_invalid($errors, 'threshold') ?>><?= field_error($errors, 'threshold') ?><p class="hint">A count, a percentage, or standard deviations (2 is common).</p></div>
          <div class="field"><label for="f-window_days">Window</label><select class="input" id="f-window_days" name="window_days"><?php foreach ([7, 14, 28] as $w): ?><option value="<?= $w ?>"<?= selected(old($old, 'window_days', '7'), (string) $w) ?>><?= $w ?> days</option><?php endforeach; ?></select><?= field_error($errors, 'window_days') ?></div>
          <div class="field"><label for="f-min_tests">Minimum tests <span class="optional">for positivity rules</span></label><input class="input" id="f-min_tests" name="min_tests" inputmode="numeric" value="<?= e(old($old, 'min_tests', '20')) ?>"<?= aria_invalid($errors, 'min_tests') ?>><?= field_error($errors, 'min_tests') ?></div>
        </div>
        <div class="form-actions"><button class="btn btn--primary" type="submit">Add rule</button></div>
      </form>
    </section>
  </div>
</div>
