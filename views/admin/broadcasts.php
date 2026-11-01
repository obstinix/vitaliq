<?php
use Vitaliq\Services\BroadcastService;

$driverLabel = ['none' => 'Off', 'outbox' => 'Local files (not sent)', 'log' => 'Local files (not sent)', 'smtp' => 'SMTP (live)', 'twilio' => 'Twilio (live)'];
?>
<div class="app-head">
  <div>
    <h1>Broadcasts</h1>
    <p>Send a short message to everyone, a role, or patients in one state. It arrives in the app; email, SMS and WhatsApp follow each person's own preferences.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<section class="kpis" aria-label="Outside delivery">
  <?php foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $k => $l): ?>
    <div class="kpi"><span class="kpi__value"><?= e($driverLabel[$drivers[$k]] ?? $drivers[$k]) ?></span><span class="kpi__label"><?= e($l) ?> channel</span></div>
  <?php endforeach; ?>
  <div class="kpi<?= $queue['failed'] > 0 ? ' kpi--alert' : '' ?>"><span class="kpi__value"><?= fmt_number($queue['queued']) ?><small>queued</small></span><span class="kpi__label">Deliveries</span><span class="kpi__note"><?= fmt_number($queue['sent']) ?> sent · <?= fmt_number($queue['simulated']) ?> local · <?= fmt_number($queue['failed']) ?> failed</span></div>
</section>
<div class="vq-grid">
  <section class="panel span-5" aria-labelledby="bc-new">
    <div class="panel__head"><h2 id="bc-new">New broadcast</h2></div>
    <form method="post" action="<?= e(url('/admin/broadcasts')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label for="f-title">Title</label><input class="input" id="f-title" name="title" maxlength="140" value="<?= e(old($old, 'title')) ?>"<?= aria_invalid($errors, 'title') ?>><?= field_error($errors, 'title') ?></div>
        <div class="field"><label for="f-body">Message</label><textarea class="input" id="f-body" name="body" rows="3" maxlength="300"<?= aria_invalid($errors, 'body') ?>><?= e(old($old, 'body')) ?></textarea><?= field_error($errors, 'body') ?></div>
        <div class="field"><label for="f-audience">Audience</label><select class="input" id="f-audience" name="audience"<?= aria_invalid($errors, 'audience') ?>><?php foreach (BroadcastService::AUDIENCES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'audience'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'audience') ?></div>
        <div class="field"><label for="f-audience_value">Role or state <span class="optional">for "One role" or "Patients in one state"</span></label>
          <select class="input" id="f-audience_value" name="audience_value"<?= aria_invalid($errors, 'audience_value') ?>>
            <option value="">—</option>
            <optgroup label="Roles"><?php foreach (['patient' => 'Patients', 'phlebotomist' => 'Phlebotomists', 'lab' => 'Laboratory staff', 'officer' => 'Public-health officers', 'qa' => 'QA analysts', 'admin' => 'Administrators'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'audience_value'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></optgroup>
            <optgroup label="States"><?php foreach ($states as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'audience_value'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></optgroup>
          </select><?= field_error($errors, 'audience_value') ?></div>
        <div class="field"><label for="f-link_path">Link <span class="optional">optional, a path on this site</span></label><input class="input" id="f-link_path" name="link_path" maxlength="190" placeholder="/announcements" value="<?= e(old($old, 'link_path')) ?>"<?= aria_invalid($errors, 'link_path') ?>><?= field_error($errors, 'link_path') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit" data-busy-label="Sending…">Send broadcast</button></div>
    </form>
  </section>
  <section class="panel span-7" aria-labelledby="bc-recent">
    <div class="panel__head"><h2 id="bc-recent">Sent</h2><span class="meta">Last 30</span></div>
    <?php if ($recent === []): ?>
      <p class="empty">No broadcasts yet.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($recent as $b): ?>
          <li><span class="grow"><?= e($b['title']) ?><span class="sub"><?= e($b['body']) ?></span><span class="sub"><?= e(BroadcastService::AUDIENCES[$b['audience']]) ?><?= $b['audience_value'] ? ' (' . e($b['audience_value']) . ')' : '' ?> · <?= e($b['full_name'] ?? '—') ?></span></span>
            <span class="end num"><?= fmt_number($b['recipients']) ?><span class="sub"><?= e(fmt_datetime($b['created_at'])) ?></span></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
