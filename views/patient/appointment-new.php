<div class="app-head">
  <div>
    <h1>Book an appointment</h1>
    <p>For <?= e($profile['full_name']) ?>. Slots are 20 minutes, 8:00 am to 8:00 pm, up to 60 days ahead.</p>
  </div>
</div>
<?= $view->partial('profile-switch', ['profiles' => $profiles, 'profile' => $profile, 'base' => '/patient/appointments/new']) ?>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<section class="panel">
  <form method="post" action="<?= e(url('/patient/appointments/new')) ?>" class="form" novalidate>
    <?= csrf_field() ?><?= profile_field($profile) ?>
    <div class="form-grid form-grid--2">
      <div class="field span-all"><label for="f-kind">Type</label>
        <select class="input" id="f-kind" name="kind"<?= aria_invalid($errors, 'kind') ?>><option value="">Choose</option>
          <?php foreach ($kinds as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'kind'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
        </select><?= field_error($errors, 'kind') ?>
        <p class="hint">Teleconsultations take place online; you get a private video room link.</p></div>
      <div class="field span-all"><label for="f-facility_id">Facility <span class="optional">not needed for a teleconsultation</span></label>
        <select class="input" id="f-facility_id" name="facility_id"<?= aria_invalid($errors, 'facility_id') ?>><option value="">Choose</option>
          <?php foreach ($facilities as $f): ?><option value="<?= (int) $f['id'] ?>"<?= selected(old($old, 'facility_id'), (string) $f['id']) ?>><?= e($f['name'] . ', ' . $f['city']) ?></option><?php endforeach; ?>
        </select><?= field_error($errors, 'facility_id') ?>
        <?php if ($facilities === []): ?><p class="hint">No facilities are open for booking yet; only teleconsultations can be booked.</p><?php endif; ?></div>
      <div class="field"><label for="f-date">Date</label><input class="input" type="date" id="f-date" name="date" min="<?= e($today) ?>" max="<?= e($maxDate) ?>" value="<?= e(old($old, 'date')) ?>"<?= aria_invalid($errors, 'date') ?>><?= field_error($errors, 'date') ?></div>
      <div class="field"><label for="f-slot">Time</label>
        <select class="input" id="f-slot" name="slot"<?= aria_invalid($errors, 'slot') ?>><option value="">Choose</option>
          <?php foreach ($slots as $s): ?><option value="<?= e($s) ?>"<?= selected(old($old, 'slot'), $s) ?>><?= e(date('g:i a', (int) strtotime('2000-01-01 ' . $s))) ?></option><?php endforeach; ?>
        </select><?= field_error($errors, 'slot') ?></div>
      <div class="field span-all"><label for="f-reason">Reason <span class="optional">optional</span></label><textarea class="input" id="f-reason" name="reason" rows="3" maxlength="300"><?= e(old($old, 'reason')) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="btn btn--primary" type="submit" data-busy-label="Booking…">Book appointment</button><a href="<?= e(url('/patient/appointments')) ?>">Cancel</a></div>
  </form>
</section>
