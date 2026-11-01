<?php
$now = app()->clock->nowSql();
?>
<div class="app-head">
  <div>
    <h1>QR health ID</h1>
    <p>A QR code that opens a read-only emergency card for <?= e($profile['full_name']) ?>: blood group, allergies, conditions, medicines and emergency contacts. Revoke it at any time.</p>
  </div>
</div>
<?= $view->partial('profile-switch', ['profiles' => $profiles, 'profile' => $profile, 'base' => '/patient/health-id']) ?>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>

<?php if ($issued !== null): ?>
  <section class="panel" aria-labelledby="qr-new">
    <div class="panel__head"><h2 id="qr-new">Your new health ID</h2><span class="meta">Shown once. Save or print it now.</span></div>
    <div class="vq-grid">
      <div class="span-4"><div class="qr-box"><?= $qr ?></div></div>
      <div class="span-8">
        <p>Anyone who scans this code sees the emergency card. The link is not stored, so if you lose it, revoke this ID and create a new one.</p>
        <p><a class="ref" href="<?= e($issued['url']) ?>"><?= e($issued['url']) ?></a></p>
        <button class="btn btn--secondary" type="button" data-print>Print</button>
      </div>
    </div>
  </section>
<?php endif; ?>

<div class="vq-grid">
  <section class="panel span-7" aria-labelledby="qr-list">
    <div class="panel__head"><h2 id="qr-list">Health IDs</h2><span class="meta">Up to 3 active</span></div>
    <?php if ($tokens === []): ?>
      <p class="empty">No health IDs yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Label</th><th scope="col">State</th><th scope="col" class="num">Views</th><th scope="col">Expires</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
          <tbody>
            <?php foreach ($tokens as $t): ?>
              <?php $state = $t['revoked_at'] !== null ? 'revoked' : (($t['expires_at'] !== null && $t['expires_at'] <= $now) ? 'expired' : 'active'); ?>
              <tr>
                <td data-label="Label"><?= e($t['label']) ?><span class="sub">Created <?= e(fmt_date($t['created_at'])) ?></span></td>
                <td data-label="State"><span class="status status--<?= e($state === 'expired' ? 'cancelled' : $state) ?>"><?= e(ucfirst($state)) ?></span></td>
                <td data-label="Views" class="num"><?= (int) $t['use_count'] ?><span class="sub"><?= $t['last_used_at'] ? e(fmt_date($t['last_used_at'])) : '—' ?></span></td>
                <td data-label="Expires"><?= $t['expires_at'] ? e(fmt_date($t['expires_at'])) : 'No expiry' ?></td>
                <td data-label="Actions"><?php if ($state === 'active'): ?>
                  <form method="post" action="<?= e(url('/patient/health-id/' . (int) $t['id'] . '/revoke')) ?>"><?= csrf_field() ?><?= profile_field($profile) ?><button class="btn btn--danger btn--small" type="submit">Revoke</button></form>
                <?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <section class="panel span-5" aria-labelledby="qr-make">
    <div class="panel__head"><h2 id="qr-make">Create a health ID</h2></div>
    <form method="post" action="<?= e(url('/patient/health-id')) ?>" novalidate>
      <?= csrf_field() ?><?= profile_field($profile) ?>
      <div class="form-grid">
        <div class="field"><label for="f-label">Label</label><input class="input" id="f-label" name="label" maxlength="60" placeholder="Wallet card"<?= aria_invalid($errors, 'label') ?>><?= field_error($errors, 'label') ?></div>
        <div class="field"><label for="f-days">Expires after</label>
          <select class="input" id="f-days" name="days"<?= aria_invalid($errors, 'days') ?>>
            <option value="">Never (until revoked)</option><option value="1">1 day</option><option value="30">30 days</option><option value="90">90 days</option><option value="365">1 year</option>
          </select><?= field_error($errors, 'days') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Create QR code</button></div>
    </form>
    <p class="hint mt-4">The card never shows test results, addresses or documents.</p>
  </section>
</div>
