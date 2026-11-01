<?php
use Vitaliq\Services\AppointmentService;

$a = $appointment;
?>
<div class="app-head">
  <div>
    <h1><?= e(AppointmentService::KINDS[$a['kind']]) ?></h1>
    <p><?= e($a['full_name']) ?> · <?= e(fmt_datetime($a['starts_at'])) ?> · <?= (int) $a['duration_min'] ?> minutes</p>
  </div>
  <span class="status status--<?= e($a['status']) ?>"><?= e(AppointmentService::STATUSES[$a['status']]) ?></span>
</div>
<div class="vq-grid">
  <section class="panel span-7">
    <dl class="details">
      <dt>Where</dt><dd><?= $a['facility_name'] ? e($a['facility_name'] . ', ' . $a['facility_city']) : 'Online' ?></dd>
      <dt>When</dt><dd><time datetime="<?= e(iso_datetime($a['starts_at'])) ?>"><?= e(fmt_datetime($a['starts_at'])) ?></time></dd>
      <dt>Reason</dt><dd><?= $a['reason'] ? e($a['reason']) : '<span class="faint">—</span>' ?></dd>
      <?php if ($a['cancel_reason']): ?><dt>Cancellation note</dt><dd><?= e($a['cancel_reason']) ?></dd><?php endif; ?>
      <dt>Booked</dt><dd><?= e(fmt_datetime($a['created_at'])) ?></dd>
    </dl>
  </section>
  <div class="span-5 stack">
    <?php if ($a['kind'] === 'teleconsultation' && $a['meeting_url'] && in_array($a['status'], ['scheduled', 'confirmed'], true)): ?>
      <section class="panel">
        <div class="panel__head"><h2>Video room</h2></div>
        <p>Join from a phone or computer a few minutes before the start. The room name is private to this appointment.</p>
        <a class="btn btn--primary" href="<?= e($a['meeting_url']) ?>" target="_blank" rel="noopener noreferrer">Join the video room</a>
        <p class="hint mt-4">Opens on an external video service (<?= e((string) parse_url((string) $a['meeting_url'], PHP_URL_HOST)) ?>).</p>
      </section>
    <?php endif; ?>
    <?php if ($canCancel): ?>
      <details class="confirm confirm--danger">
        <summary>Cancel this appointment</summary>
        <div class="confirm__body">
          <form method="post" action="<?= e(url('/patient/appointments/' . (int) $a['id'] . '/cancel')) ?>">
            <?= csrf_field() ?>
            <div class="field"><label for="f-reason">Reason <span class="optional">optional</span></label><input class="input" id="f-reason" name="reason" maxlength="300"></div>
            <button class="btn btn--danger mt-4" type="submit">Cancel appointment</button>
          </form>
        </div>
      </details>
    <?php elseif (in_array($a['status'], ['scheduled', 'confirmed'], true)): ?>
      <p class="callout callout--note">It is less than an hour to the start, so this appointment can no longer be cancelled online. Please call the facility.</p>
    <?php endif; ?>
    <a href="<?= e(url('/patient/appointments')) ?>">All appointments</a>
  </div>
</div>
