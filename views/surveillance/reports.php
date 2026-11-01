<div class="app-head">
  <div>
    <h1>Reports</h1>
    <p>Download counts, positivity and 95% intervals as a spreadsheet (CSV) or a printable PDF.</p>
  </div>
</div>
<section class="panel">
  <form method="get" action="<?= e(url('/surveillance/reports/export')) ?>" class="form">
    <div class="form-grid form-grid--2">
      <div class="field"><label for="r-disease">Disease</label><select class="input" id="r-disease" name="disease"><?php foreach ($diseases as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($disease, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="r-days">Period</label><select class="input" id="r-days" name="days"><?php foreach ([7 => 'Last 7 days', 14 => 'Last 14 days', 28 => 'Last 28 days', 90 => 'Last 90 days', 365 => 'Last 365 days'] as $k => $l): ?><option value="<?= $k ?>"<?= selected((string) $days, (string) $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="r-state">State</label><select class="input" id="r-state" name="state"><option value="">All states</option><?php foreach ($states as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($state, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="r-by">Break down by</label><select class="input" id="r-by" name="by"><?php foreach (['state' => 'State', 'day' => 'Day', 'age' => 'Age band', 'sex' => 'Sex', 'facility' => 'Facility', 'source' => 'Data source'] as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-actions">
      <button class="btn btn--primary" type="submit" name="format" value="pdf">Download PDF</button>
      <button class="btn btn--secondary" type="submit" name="format" value="csv">Download CSV</button>
    </div>
  </form>
  <p class="hint mt-4">Each download is recorded in the audit log. Reports hold counts only, never names or contact details.</p>
</section>
