<?php
use Vitaliq\Services\AppointmentService;
use Vitaliq\Support\Labels;

/*
 * Question: "What is happening with my tests, and what is next?"
 * Biggest block: requests in progress, each drawn on the sample's path.
 */
$steps = ['submitted' => 'Requested', 'sample_collected' => 'Collected', 'in_progress' => 'Testing', 'completed' => 'Recorded', 'result_available' => 'Released'];
$order = ['submitted' => 0, 'assigned' => 0, 'sample_collected' => 1, 'in_progress' => 2, 'completed' => 3, 'result_available' => 4];
$next = $appointments[0] ?? null;
?>
<div class="app-head">
  <div>
    <h1>Your health</h1>
    <p>
      <?= count($active) === 0 ? 'No tests in progress' : (count($active) === 1 ? 'One test in progress' : count($active) . ' tests in progress') ?>
      · <?= $next === null ? 'no appointment booked' : 'next appointment ' . e(fmt_date($next['starts_at'], 'D j M, g:i a')) ?>.
    </p>
  </div>
  <div class="cluster">
    <a class="btn btn--secondary" href="<?= e(url('/patient/appointments/new')) ?>">Book an appointment</a>
    <a class="btn btn--primary" href="<?= e(url('/patient/requests/new')) ?>">Request a test</a>
  </div>
</div>

<section class="kpis" aria-label="Your record at a glance">
  <div class="kpi"><a href="<?= e(url('/patient/requests')) ?>"><span class="kpi__value"><?= count($active) ?></span><span class="kpi__label">Tests in progress</span></a></div>
  <div class="kpi"><a href="<?= e(url('/patient/appointments')) ?>"><span class="kpi__value"><?= count($appointments) ?></span><span class="kpi__label">Upcoming appointments</span></a></div>
  <div class="kpi"><a href="<?= e(url('/patient/passport')) ?>"><span class="kpi__value"><?= (int) $passportFilled ?><small>of <?= (int) $passportOf ?></small></span><span class="kpi__label">Passport sections filled</span></a></div>
  <div class="kpi"><a href="<?= e(url('/patient/family')) ?>"><span class="kpi__value"><?= (int) $family ?></span><span class="kpi__label">Family profiles</span></a></div>
</section>

<div class="vq-grid">
  <div class="span-8 stack">
    <section class="panel" aria-labelledby="current-title">
      <div class="panel__head"><h2 id="current-title">Tests in progress</h2><a href="<?= e(url('/patient/requests')) ?>">All tests</a></div>
      <?php if ($active === []): ?>
        <p class="empty">Nothing in progress. When you request a test, each step appears here as it happens.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($active as $r): ?>
            <?php $at = $order[$r['status']] ?? 0; ?>
            <li class="request-row">
              <div class="grow">
                <a class="ref" href="<?= e(url('/patient/requests/' . (int) $r['id'])) ?>"><?= e($r['reference']) ?></a>
                <span class="sub"><?= e(Labels::get(Labels::TEST_TYPES_SHORT, $r['test_type'])) ?> · <?= e(status_label($r['status'])) ?></span>
                <ol class="path mt-4" aria-label="Progress">
                  <?php $i = 0; foreach ($steps as $label): ?>
                    <li class="<?= $i < $at ? 'done' : ($i === $at ? 'done now' : '') ?>"><?= e($label) ?></li>
                  <?php $i++; endforeach; ?>
                </ol>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="past-title">
      <div class="panel__head"><h2 id="past-title">Recent results</h2><a href="<?= e(url('/patient/timeline')) ?>">Timeline</a></div>
      <?php if ($past === []): ?>
        <p class="empty">Released, cancelled and rejected tests will be listed here.</p>
      <?php else: ?>
        <?= $view->partial('requests-table', ['rows' => $past]) ?>
      <?php endif; ?>
    </section>
  </div>

  <div class="span-4 stack">
    <section class="panel" aria-labelledby="appt-title">
      <div class="panel__head"><h2 id="appt-title">Upcoming appointments</h2><a href="<?= e(url('/patient/appointments', ['view' => 'calendar'])) ?>">Calendar</a></div>
      <?php if ($appointments === []): ?>
        <p class="empty">None booked.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($appointments as $a): ?>
            <li><span class="grow"><a href="<?= e(url('/patient/appointments/' . (int) $a['id'])) ?>"><?= e(AppointmentService::KINDS[$a['kind']]) ?></a><span class="sub"><?= e($a['full_name']) ?></span></span>
              <time class="end num" datetime="<?= e(iso_datetime($a['starts_at'])) ?>"><?= e(fmt_date($a['starts_at'], 'j M')) ?><span class="sub"><?= e(fmt_date($a['starts_at'], 'g:i a')) ?></span></time></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="notes-title">
      <div class="panel__head"><h2 id="notes-title">Notifications</h2><a href="<?= e(url('/notifications')) ?>">All</a></div>
      <?php if ($notifications === []): ?>
        <p class="empty">No notifications yet.</p>
      <?php else: ?>
        <ul class="note-list">
          <?php foreach (array_slice($notifications, 0, 4) as $n): ?>
            <li class="<?= $n['read_at'] === null ? 'unread' : '' ?>">
              <a href="<?= e(url('/notifications/' . (int) $n['id'])) ?>">
                <span class="note-title"><?= e($n['title']) ?><?= $n['read_at'] === null ? '<span class="visually-hidden"> (unread)</span>' : '' ?></span>
                <span class="note-body"><?= e($n['body']) ?></span>
                <time class="note-time" datetime="<?= e(iso_datetime($n['created_at'])) ?>"><?= e(fmt_datetime($n['created_at'])) ?></time>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="profile-title">
      <div class="panel__head"><h2 id="profile-title">Your details</h2><a href="<?= e(url('/account')) ?>">Edit</a></div>
      <?php if ($patient !== null): ?>
        <dl class="details">
          <dt>Name</dt><dd><?= e($patient['full_name']) ?></dd>
          <dt>Date of birth</dt><dd><?= e(fmt_date($patient['date_of_birth'])) ?></dd>
          <dt>Mobile</dt><dd class="num"><?= e(format_mobile($patient['mobile'])) ?></dd>
        </dl>
      <?php endif; ?>
      <p class="hint mt-4">Tested before you registered? <a href="<?= e(url('/patient/link')) ?>">Link that request</a>.</p>
    </section>
  </div>
</div>
