<?php
use Vitaliq\Services\DeliveryService;

$u = $currentUser;
$mask = static function (?string $v): string {
    if ($v === null) {
        return '—';
    }
    if (str_contains($v, '@')) {
        [$a, $b] = explode('@', $v, 2);
        return mb_substr($a, 0, 2) . '…@' . $b;
    }
    return substr($v, 0, 5) . '…' . substr($v, -2);
};
$statusLabel = ['sent' => 'Sent', 'simulated' => 'Written locally, not sent', 'queued' => 'Queued', 'failed' => 'Failed', 'skipped' => 'Skipped'];
?>
<div class="app-head">
  <div>
    <h1><?= e(t('Preferences')) ?></h1>
    <p><?= e(t('In-app notifications are always on. Choose what else you want, and where.')) ?></p>
  </div>
</div>
<form method="post" action="<?= e(url('/account/preferences')) ?>">
  <?= csrf_field() ?>
  <section class="panel" aria-labelledby="pref-ui">
    <div class="panel__head"><h2 id="pref-ui"><?= e(t('Interface')) ?></h2></div>
    <div class="form-grid form-grid--2">
      <div class="field">
        <label for="f-locale"><?= e(t('Language')) ?></label>
        <select class="input" id="f-locale" name="locale">
          <?php foreach ($locales as $code => $name): ?><option value="<?= e($code) ?>" lang="<?= e($code) ?>"<?= selected((string) ($u['locale'] ?? 'en'), $code) ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
        <p class="hint">Translated: navigation, menus, the patient overview and this page. Other pages stay in English for now.</p>
      </div>
      <div class="field">
        <span class="field-label"><?= e(t('Accessibility mode')) ?></span>
        <label class="checkbox-line"><input type="checkbox" name="accessibility_mode" value="1"<?= checked((int) ($u['accessibility_mode'] ?? 0) === 1) ?>> <span><?= e(t('Larger text, stronger outlines and underlined links on every page.')) ?></span></label>
      </div>
    </div>
  </section>

  <section class="panel" aria-labelledby="pref-ch">
    <div class="panel__head"><h2 id="pref-ch"><?= e(t('Messages outside the app')) ?></h2></div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th scope="col">Category</th>
            <?php foreach (DeliveryService::CHANNELS as $ch => $label): ?>
              <th scope="col"><?= e(t($label)) ?><span class="sub"><?= e($mask($recipients[$ch] ?? null)) ?></span>
                <span class="sub"><?= !$channelStatus[$ch]['enabled'] ? 'Not set up on this server' : ($channelStatus[$ch]['live'] ? 'Live' : 'Local test mode') ?></span></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach (DeliveryService::CATEGORIES as $cat => $label): ?>
            <tr><th scope="row"><?= e(t($label)) ?></th>
              <?php foreach (DeliveryService::CHANNELS as $ch => $chLabel): ?>
                <td><label class="checkbox-line"><input type="checkbox" name="prefs[<?= e($cat) ?>][<?= e($ch) ?>]" value="1"<?= checked($prefs[$cat][$ch]) ?><?= ($recipients[$ch] ?? null) === null ? ' disabled' : '' ?>><span class="visually-hidden"><?= e(t($label) . ', ' . t($chLabel)) ?></span></label></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint mt-4">A channel marked "Not set up on this server" keeps your choice but sends nothing until an administrator configures it. SMS and WhatsApp use the mobile number on your profile.</p>
    <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Save')) ?></button></div>
  </section>
</form>

<section class="panel" aria-labelledby="pref-log">
  <div class="panel__head"><h2 id="pref-log">Recent outside deliveries</h2></div>
  <?php if ($recent === []): ?>
    <p class="empty">Nothing has been sent outside the app.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th scope="col">Notification</th><th scope="col">Channel</th><th scope="col">Result</th><th scope="col" class="num">When</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $d): ?>
          <tr><td><?= e($d['title']) ?></td><td><?= e(DeliveryService::CHANNELS[$d['channel']]) ?></td>
            <td><span class="status status--<?= e(['sent' => 'ok', 'simulated' => 'pending', 'queued' => 'pending', 'failed' => 'critical', 'skipped' => 'cancelled'][$d['status']]) ?>"><?= e($statusLabel[$d['status']]) ?></span><?= $d['last_error'] ? '<span class="sub">' . e($d['last_error']) . '</span>' : '' ?></td>
            <td class="num"><?= e(fmt_datetime($d['sent_at'] ?? $d['created_at'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
