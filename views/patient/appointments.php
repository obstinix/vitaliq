<?php
use Vitaliq\Services\AppointmentService;

$K = AppointmentService::KINDS;
?>
<div class="app-head">
  <div>
    <h1>Appointments</h1>
    <p><?= $mode === 'calendar' ? e($grid['label']) : (count($upcoming) === 0 ? 'No upcoming appointments.' : count($upcoming) . ' upcoming, next on ' . e(fmt_datetime($upcoming[0]['starts_at'])) . '.') ?></p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/patient/appointments/new')) ?>">Book an appointment</a>
</div>
<nav class="tabs" aria-label="Appointment views">
  <a href="<?= e(url('/patient/appointments')) ?>"<?= $mode === 'list' ? ' aria-current="page"' : '' ?>>List</a>
  <a href="<?= e(url('/patient/appointments', ['view' => 'calendar'])) ?>"<?= $mode === 'calendar' ? ' aria-current="page"' : '' ?>>Calendar</a>
</nav>

<?php if ($mode === 'calendar'): ?>
  <?= $view->partial('calendar', ['grid' => $grid, 'base' => '/patient/appointments', 'query' => ['view' => 'calendar'], 'link' => '/patient/appointments/']) ?>
<?php else: ?>
  <div class="vq-grid">
    <section class="panel span-7" aria-labelledby="ap-up">
      <div class="panel__head"><h2 id="ap-up">Upcoming</h2><span class="meta"><?= count($upcoming) ?></span></div>
      <?php if ($upcoming === []): ?>
        <p class="empty">Nothing booked. <a href="<?= e(url('/patient/appointments/new')) ?>">Book an appointment</a>.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($upcoming as $a): ?>
            <li><span class="grow"><a href="<?= e(url('/patient/appointments/' . (int) $a['id'])) ?>"><?= e($K[$a['kind']]) ?></a><span class="sub"><?= e($a['full_name']) ?> · <?= e($a['facility_name'] ?? 'Online') ?></span></span>
              <span class="status status--<?= e($a['status']) ?>"><?= e(AppointmentService::STATUSES[$a['status']]) ?></span>
              <time class="end num" datetime="<?= e(iso_datetime($a['starts_at'])) ?>"><?= e(fmt_date($a['starts_at'], 'D j M')) ?><span class="sub"><?= e(fmt_date($a['starts_at'], 'g:i a')) ?></span></time></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <section class="panel span-5" aria-labelledby="ap-past">
      <div class="panel__head"><h2 id="ap-past">Past and cancelled</h2><span class="meta"><?= count($past) ?></span></div>
      <?php if ($past === []): ?>
        <p class="empty">No past appointments.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach (array_slice($past, 0, 8) as $a): ?>
            <li><span class="grow"><a href="<?= e(url('/patient/appointments/' . (int) $a['id'])) ?>"><?= e($K[$a['kind']]) ?></a><span class="sub"><?= e($a['full_name']) ?></span></span>
              <span class="status status--<?= e($a['status']) ?>"><?= e(AppointmentService::STATUSES[$a['status']]) ?></span>
              <span class="end num"><?= e(fmt_date($a['starts_at'], 'Y-m-d')) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>
