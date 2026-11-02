<?php
use Vitaliq\Support\Labels;

$w = $week;
$base = $admin ? '/admin/schedule' : '/staff/schedule';
?>
<div class="app-head">
  <div>
    <h1><?= e($title) ?></h1>
    <p>Week of <?= e(fmt_date($w['start'], 'j F Y')) ?><?= $admin ? '' : '. Shifts are set by an administrator.' ?></p>
  </div>
  <div class="cluster">
    <a class="btn btn--secondary btn--small" href="<?= e(url($base, ['week' => $w['prev']])) ?>">Previous week</a>
    <a class="btn btn--secondary btn--small" href="<?= e(url($base, ['week' => $today])) ?>">This week</a>
    <a class="btn btn--secondary btn--small" href="<?= e(url($base, ['week' => $w['next']])) ?>">Next week</a>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<section class="panel">
  <?php if ($w['rows'] === []): ?>
    <p class="empty">No shifts this week.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table rota">
        <thead><tr><th scope="col">Person</th><?php foreach ($w['days'] as $d): ?><th scope="col"<?= $d === $today ? ' class="today"' : '' ?>><?= e(fmt_date($d, 'D j')) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($w['rows'] as $r): ?>
            <tr><th scope="row"><?= e($r['name']) ?><span class="sub"><?= e(Labels::get(Labels::ROLES, $r['role'])) ?></span></th>
              <?php foreach ($w['days'] as $d): ?>
                <td<?= $d === $today ? ' class="today"' : '' ?>>
                  <?php foreach ($r['days'][$d] as $s): ?>
                    <div class="shift shift--<?= e($s['duty']) ?>"><strong class="num"><?= e(substr((string) $s['starts_at'], 11, 5)) ?>–<?= e(substr((string) $s['ends_at'], 11, 5)) ?></strong>
                      <span class="sub"><?= e($duties[$s['duty']]) ?><?= $s['facility_name'] ? ' · ' . e($s['facility_name']) : '' ?></span>
                      <?php if ($admin): ?><form method="post" action="<?= e(url('/admin/schedule/' . (int) $s['id'] . '/remove')) ?>"><?= csrf_field() ?><input type="hidden" name="week" value="<?= e($w['start']) ?>"><button class="link-button small" type="submit" aria-label="Remove this shift">Remove</button></form><?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php if ($admin): ?>
  <section class="panel" aria-labelledby="add-shift">
    <div class="panel__head"><h2 id="add-shift">Add a shift</h2></div>
    <form method="post" action="<?= e(url('/admin/schedule')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid form-grid--3">
        <div class="field"><label for="f-user_id">Staff member</label><select class="input" id="f-user_id" name="user_id"<?= aria_invalid($errors, 'user_id') ?>><option value="">Choose</option><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected(old($old, 'user_id'), (string) $s['id']) ?>><?= e($s['full_name'] . ' (' . Labels::get(Labels::ROLES, $s['role']) . ')') ?></option><?php endforeach; ?></select><?= field_error($errors, 'user_id') ?></div>
        <div class="field"><label for="f-duty">Duty</label><select class="input" id="f-duty" name="duty"<?= aria_invalid($errors, 'duty') ?>><?php foreach ($duties as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'duty'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'duty') ?></div>
        <div class="field"><label for="f-facility_id">Facility <span class="optional">optional</span></label><select class="input" id="f-facility_id" name="facility_id"><option value="">—</option><?php foreach ($facilities as $f): ?><option value="<?= (int) $f['id'] ?>"<?= selected(old($old, 'facility_id'), (string) $f['id']) ?>><?= e($f['name']) ?></option><?php endforeach; ?></select><?= field_error($errors, 'facility_id') ?></div>
        <div class="field"><label for="f-date">Date</label><input class="input" type="date" id="f-date" name="date" value="<?= e(old($old, 'date', $today)) ?>"<?= aria_invalid($errors, 'date') ?>><?= field_error($errors, 'date') ?></div>
        <div class="field"><label for="f-start">Starts</label><input class="input" type="time" id="f-start" name="start" value="<?= e(old($old, 'start', '08:00')) ?>"<?= aria_invalid($errors, 'start') ?>><?= field_error($errors, 'start') ?></div>
        <div class="field"><label for="f-end">Ends <span class="optional">earlier than the start means the next day</span></label><input class="input" type="time" id="f-end" name="end" value="<?= e(old($old, 'end', '16:00')) ?>"<?= aria_invalid($errors, 'end') ?>><?= field_error($errors, 'end') ?></div>
        <div class="field span-all"><label for="f-note">Note <span class="optional">optional</span></label><input class="input" id="f-note" name="note" maxlength="200" value="<?= e(old($old, 'note')) ?>"></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Add shift</button></div>
    </form>
  </section>
<?php endif; ?>
