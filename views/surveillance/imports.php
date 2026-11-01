<?php
$labels = ['previewed' => ['Waiting for review', 'pending'], 'committed' => ['Imported', 'active'], 'rolled_back' => ['Rolled back', 'cancelled'], 'discarded' => ['Discarded', 'cancelled']];
?>
<div class="app-head">
  <div>
    <h1>Imports</h1>
    <p>Load aggregate counts from district, state or partner returns. Every file is checked row by row and shown to you before anything is written.</p>
  </div>
  <a class="btn btn--secondary" href="<?= e(url('/surveillance/imports/template.csv')) ?>">Download CSV template</a>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="span-8">
    <?php if ($imports === []): ?>
      <p class="empty">No files imported yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">File</th><th scope="col">Source</th><th scope="col" class="num">Rows</th><th scope="col" class="num">Rejected</th><th scope="col">Status</th><th scope="col" class="num">Uploaded</th></tr></thead>
          <tbody>
            <?php foreach ($imports as $i): ?>
              <tr>
                <td data-label="File"><a href="<?= e(url('/surveillance/imports/' . (int) $i['id'])) ?>"><?= e($i['filename']) ?></a><span class="sub"><?= e(strtoupper($i['format'])) ?> · <?= e($i['full_name'] ?? '—') ?></span></td>
                <td data-label="Source"><?= e($i['source_name']) ?></td>
                <td data-label="Rows" class="num"><?= fmt_number($i['rows_total']) ?></td>
                <td data-label="Rejected" class="num"><?= fmt_number($i['rows_failed']) ?></td>
                <td data-label="Status"><span class="status status--<?= e($labels[$i['status']][1]) ?>"><?= e($labels[$i['status']][0]) ?></span></td>
                <td data-label="Uploaded" class="num"><?= e(fmt_datetime($i['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <section class="panel span-4" aria-labelledby="up">
    <div class="panel__head"><h2 id="up">Upload a file</h2></div>
    <form method="post" action="<?= e(url('/surveillance/imports')) ?>" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?><input type="hidden" name="MAX_FILE_SIZE" value="5242880">
      <div class="form-grid">
        <div class="field"><label for="f-source_name">Source</label><input class="input" id="f-source_name" name="source_name" maxlength="120" placeholder="State IDSP weekly return" value="<?= e(old($old, 'source_name')) ?>"<?= aria_invalid($errors, 'source_name') ?>><?= field_error($errors, 'source_name') ?></div>
        <div class="field"><label for="f-file">CSV or JSON file, up to 5 MB</label><input class="input" type="file" id="f-file" name="file" accept=".csv,.json,text/csv,application/json"<?= aria_invalid($errors, 'file') ?>><?= field_error($errors, 'file') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit" data-busy-label="Checking…">Check file</button></div>
    </form>
    <p class="hint mt-4">Columns: disease, date (YYYY-MM-DD), state (code or name), tests, positives; optionally sex, age_band and facility (code). A JSON file is a list of objects with the same keys.</p>
  </section>
</div>
