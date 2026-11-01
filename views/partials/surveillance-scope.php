<?php
/**
 * Scope row for surveillance screens: disease, period and state.
 * Expects $base, $disease, $days, $state, $diseases, $states; $noState hides the state select.
 */
?>
<form class="cluster scope-row" method="get" action="<?= e(url($base)) ?>">
  <label class="visually-hidden" for="s-disease">Disease</label>
  <select class="input" id="s-disease" name="disease"><?php foreach ($diseases as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($disease, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <label class="visually-hidden" for="s-days">Period</label>
  <select class="input" id="s-days" name="days"><?php foreach ([7 => 'Last 7 days', 14 => 'Last 14 days', 28 => 'Last 28 days', 90 => 'Last 90 days', 365 => 'Last 365 days'] as $k => $l): ?><option value="<?= $k ?>"<?= selected((string) $days, (string) $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <?php if (empty($noState)): ?>
    <label class="visually-hidden" for="s-state">State</label>
    <select class="input" id="s-state" name="state"><option value="">All states</option><?php foreach ($states as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($state, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <button class="btn btn--secondary" type="submit">Show</button>
</form>
