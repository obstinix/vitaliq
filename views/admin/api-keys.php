<?php
$now = app()->clock->nowSql();
?>
<div class="app-head">
  <div>
    <h1>API keys</h1>
    <p>Keys for the public API and FHIR endpoints. A key is shown once; only its hash is kept. <a href="<?= e(url('/developers')) ?>">Developer documentation</a>.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<?php if ($issued !== null): ?>
  <section class="panel" aria-labelledby="new-key">
    <div class="panel__head"><h2 id="new-key">New key</h2><span class="meta">Copy it now; it will not be shown again</span></div>
    <p class="ref api-key"><?= e($issued['key']) ?></p>
  </section>
<?php endif; ?>
<div class="vq-grid">
  <section class="span-7">
    <?php if ($keys === []): ?>
      <p class="empty">No keys issued.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Key</th><th scope="col">Scopes</th><th scope="col" class="num">Last used</th><th scope="col">State</th></tr></thead>
          <tbody>
            <?php foreach ($keys as $k): ?>
              <?php $state = $k['revoked_at'] !== null ? 'revoked' : ($k['expires_at'] !== null && $k['expires_at'] <= $now ? 'expired' : 'active'); ?>
              <tr>
                <td data-label="Key"><?= e($k['name']) ?><span class="sub ref">vq_<?= e($k['key_prefix']) ?>_…</span><span class="sub">by <?= e($k['full_name'] ?? '—') ?>, expires <?= e(fmt_date($k['expires_at'])) ?></span></td>
                <td data-label="Scopes"><?php foreach (explode(' ', (string) $k['scopes']) as $s): ?><span class="tag"><?= e($s) ?></span> <?php endforeach; ?></td>
                <td data-label="Last used" class="num"><?= $k['last_used_at'] ? e(fmt_datetime($k['last_used_at'])) : 'Never' ?></td>
                <td data-label="State"><?php if ($state === 'active'): ?><form method="post" action="<?= e(url('/admin/api-keys/' . (int) $k['id'] . '/revoke')) ?>"><?= csrf_field() ?><button class="btn btn--danger btn--small" type="submit">Revoke</button></form><?php else: ?><span class="status status--cancelled"><?= e(ucfirst($state)) ?></span><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <section class="panel span-5" aria-labelledby="issue">
    <div class="panel__head"><h2 id="issue">Issue a key</h2></div>
    <form method="post" action="<?= e(url('/admin/api-keys')) ?>" novalidate><?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label for="f-name">Who uses it</label><input class="input" id="f-name" name="name" maxlength="80" value="<?= e(old($old, 'name')) ?>"<?= aria_invalid($errors, 'name') ?>><?= field_error($errors, 'name') ?></div>
        <fieldset class="mb-0" id="f-scopes"><legend class="field-label">Scopes</legend>
          <?php foreach ($scopes as $k => $l): ?><label class="checkbox-line"><input type="checkbox" name="scopes[]" value="<?= e($k) ?>"> <span><span class="ref"><?= e($k) ?></span> · <?= e($l) ?></span></label><?php endforeach; ?>
          <?= field_error($errors, 'scopes') ?></fieldset>
        <div class="field"><label for="f-days">Expires after</label><select class="input" id="f-days" name="days"><option value="30">30 days</option><option value="90" selected>90 days</option><option value="365">1 year</option></select></div>
      </div>
      <p class="hint mt-4">fhir.read exposes personal health data. Issue it only to a system covered by a data-sharing agreement.</p>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Issue key</button></div>
    </form>
  </section>
</div>
