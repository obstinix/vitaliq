<?php
use Vitaliq\Services\AppointmentService;

$counts = array_count_values(array_column($rows, 'status'));
?>
<div class="app-head">
  <div>
    <h1>Appointments</h1>
    <p><?= e(fmt_date($day, 'l j F Y')) ?>: <?= count($rows) ?> booked<?= isset($counts['attended']) ? ', ' . (int) $counts['attended'] . ' attended' : '' ?><?= isset($counts['missed']) ? ', ' . (int) $counts['missed'] . ' missed' : '' ?>.</p>
  </div>
  <form method="get" action="<?= e(url('/admin/appointments')) ?>" class="cluster">
    <label class="visually-hidden" for="f-day">Day</label><input class="input" type="date" id="f-day" name="day" value="<?= e($day) ?>">
    <label class="visually-hidden" for="f-facility">Facility</label>
    <select class="input" id="f-facility" name="facility"><option value="0">All facilities</option>
      <?php foreach ($facilities as $f): ?><option value="<?= (int) $f['id'] ?>"<?= selected((string) $facility, (string) $f['id']) ?>><?= e($f['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn--secondary" type="submit">Show</button>
  </form>
</div>
<section class="panel" aria-labelledby="day-list">
  <div class="panel__head"><h2 id="day-list">Day list</h2><span class="meta"><?= count($rows) ?> appointments</span></div>
  <?php if ($rows === []): ?>
    <p class="empty">No appointments on this day.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead><tr><th scope="col">Time</th><th scope="col">Patient</th><th scope="col">Type</th><th scope="col">Where</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Update</span></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $a): ?>
            <tr>
              <td data-label="Time" class="num"><?= e(fmt_date($a['starts_at'], 'H:i')) ?></td>
              <td data-label="Patient"><?= e($a['full_name']) ?><span class="sub"><?= e(format_mobile($a['mobile'])) ?></span></td>
              <td data-label="Type"><?= e(AppointmentService::KINDS[$a['kind']]) ?></td>
              <td data-label="Where"><?= e($a['facility_name'] ?? 'Online') ?></td>
              <td data-label="Status"><span class="status status--<?= e($a['status']) ?>"><?= e(AppointmentService::STATUSES[$a['status']]) ?></span></td>
              <td data-label="Update">
                <?php if (in_array($a['status'], ['scheduled', 'confirmed'], true)): ?>
                  <form method="post" action="<?= e(url('/admin/appointments/' . (int) $a['id'] . '/status')) ?>" class="cluster">
                    <?= csrf_field() ?><input type="hidden" name="day" value="<?= e($day) ?>">
                    <label class="visually-hidden" for="st-<?= (int) $a['id'] ?>">New status</label>
                    <select class="input" id="st-<?= (int) $a['id'] ?>" name="status">
                      <?php foreach (($a['status'] === 'scheduled' ? ['confirmed', 'attended', 'missed', 'cancelled'] : ['attended', 'missed', 'cancelled']) as $s): ?>
                        <option value="<?= e($s) ?>"><?= e(AppointmentService::STATUSES[$s]) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn--secondary btn--small" type="submit">Save</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<div class="mt-4">
  <?= $view->partial('calendar', ['grid' => $grid, 'base' => '/admin/appointments', 'query' => ['facility' => $facility ?: null], 'link' => '/admin/appointments', 'linkDay' => true]) ?>
</div>
