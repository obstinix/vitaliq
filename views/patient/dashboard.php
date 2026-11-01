<?php
use Vitaliq\Services\AppointmentService;
use Vitaliq\Support\Labels;

/*
 * Question: "What is happening with my tests, and what is next?"
 * Biggest block: requests in progress, each drawn on the sample's path.
 */
$steps = ['submitted' => t('Requested'), 'sample_collected' => t('Collected'), 'in_progress' => t('Testing'), 'completed' => t('Recorded'), 'result_available' => t('Released')];
$order = ['submitted' => 0, 'assigned' => 0, 'sample_collected' => 1, 'in_progress' => 2, 'completed' => 3, 'result_available' => 4];
$next = $appointments[0] ?? null;
?>
<div class="app-head">
  <div>
    <h1><?= e(t('Your health')) ?></h1>
    <p>
      <?= e(count($active) === 0 ? t('No tests in progress') : (count($active) === 1 ? t('One test in progress') : t('{n} tests in progress', ['n' => count($active)]))) ?>
      · <?= e($next === null ? t('no appointment booked') : t('next appointment {when}', ['when' => fmt_date($next['starts_at'], 'D j M, g:i a')])) ?>.
    </p>
  </div>
  <div class="cluster">
    <a class="btn btn--secondary" href="<?= e(url('/patient/appointments/new')) ?>"><?= e(t('Book an appointment')) ?></a>
    <a class="btn btn--primary" href="<?= e(url('/patient/requests/new')) ?>"><?= e(t('Request a test')) ?></a>
  </div>
</div>

<section class="kpis" aria-label="Your record at a glance">
  <div class="kpi"><a href="<?= e(url('/patient/requests')) ?>"><span class="kpi__value"><?= count($active) ?></span><span class="kpi__label"><?= e(t('Tests in progress')) ?></span></a></div>
  <div class="kpi"><a href="<?= e(url('/patient/appointments')) ?>"><span class="kpi__value"><?= count($appointments) ?></span><span class="kpi__label"><?= e(t('Upcoming appointments')) ?></span></a></div>
  <div class="kpi"><a href="<?= e(url('/patient/passport')) ?>"><span class="kpi__value"><?= (int) $passportFilled ?><small>of <?= (int) $passportOf ?></small></span><span class="kpi__label"><?= e(t('Passport sections filled')) ?></span></a></div>
  <div class="kpi"><a href="<?= e(url('/patient/family')) ?>"><span class="kpi__value"><?= (int) $family ?></span><span class="kpi__label"><?= e(t('Family profiles')) ?></span></a></div>
</section>

<div class="vq-grid">
  <div class="span-8 stack">
    <section class="panel" aria-labelledby="current-title">
      <div class="panel__head"><h2 id="current-title"><?= e(t('Tests in progress')) ?></h2><a href="<?= e(url('/patient/requests')) ?>"><?= e(t('All tests')) ?></a></div>
      <?php if ($active === []): ?>
        <p class="empty"><?= e(t('Nothing in progress. When you request a test, each step appears here as it happens.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($active as $r): ?>
            <?php $at = $order[$r['status']] ?? 0; ?>
            <li class="request-row">
              <div class="grow">
                <a class="ref" href="<?= e(url('/patient/requests/' . (int) $r['id'])) ?>"><?= e($r['reference']) ?></a>
                <span class="sub"><?= e(Labels::get(Labels::TEST_TYPES_SHORT, $r['test_type'])) ?> · <?= e(status_label($r['status'])) ?></span>
                <ol class="path mt-4" aria-label="<?= e(t('Progress')) ?>">
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
      <div class="panel__head"><h2 id="past-title"><?= e(t('Recent results')) ?></h2><a href="<?= e(url('/patient/timeline')) ?>"><?= e(t('Timeline')) ?></a></div>
      <?php if ($past === []): ?>
        <p class="empty"><?= e(t('Released, cancelled and rejected tests will be listed here.')) ?></p>
      <?php else: ?>
        <?= $view->partial('requests-table', ['rows' => $past]) ?>
      <?php endif; ?>
    </section>
  </div>

  <div class="span-4 stack">
    <section class="panel" aria-labelledby="appt-title">
      <div class="panel__head"><h2 id="appt-title"><?= e(t('Upcoming appointments')) ?></h2><a href="<?= e(url('/patient/appointments', ['view' => 'calendar'])) ?>"><?= e(t('Calendar')) ?></a></div>
      <?php if ($appointments === []): ?>
        <p class="empty"><?= e(t('None booked.')) ?></p>
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
      <div class="panel__head"><h2 id="notes-title"><?= e(t('Notifications')) ?></h2><a href="<?= e(url('/notifications')) ?>"><?= e(t('All')) ?></a></div>
      <?php if ($notifications === []): ?>
        <p class="empty"><?= e(t('No notifications yet.')) ?></p>
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
      <div class="panel__head"><h2 id="profile-title"><?= e(t('Your details')) ?></h2><a href="<?= e(url('/account')) ?>"><?= e(t('Edit')) ?></a></div>
      <?php if ($patient !== null): ?>
        <dl class="details">
          <dt><?= e(t('Name')) ?></dt><dd><?= e($patient['full_name']) ?></dd>
          <dt><?= e(t('Date of birth')) ?></dt><dd><?= e(fmt_date($patient['date_of_birth'])) ?></dd>
          <dt><?= e(t('Mobile')) ?></dt><dd class="num"><?= e(format_mobile($patient['mobile'])) ?></dd>
        </dl>
      <?php endif; ?>
      <p class="hint mt-4"><?= e(t('Tested before you registered?')) ?> <a href="<?= e(url('/patient/link')) ?>"><?= e(t('Link that request')) ?></a>.</p>
    </section>
  </div>
</div>
