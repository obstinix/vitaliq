<?php
$total = array_sum(array_map(static fn ($d) => (int) $d['size_bytes'], $documents));
$size = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format(max(1, (int) round($b / 1024))) . ' KB';
?>
<div class="app-head">
  <div>
    <h1>Document vault</h1>
    <p><?= count($documents) ?> <?= count($documents) === 1 ? 'document' : 'documents' ?> for <?= e($profile['full_name']) ?><?= $documents !== [] ? ', ' . e($size($total)) . ' in total' : '' ?>. Files are encrypted before they are written to disk.</p>
  </div>
</div>
<?= $view->partial('profile-switch', ['profiles' => $profiles, 'profile' => $profile, 'base' => '/patient/documents']) ?>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="panel span-8" aria-labelledby="doc-list">
    <div class="panel__head"><h2 id="doc-list">Documents</h2><span class="meta">PDF, PNG or JPEG, up to 5 MB each</span></div>
    <?php if ($documents === []): ?>
      <p class="empty">No documents yet. Upload prescriptions, reports or discharge summaries to keep them in one place.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Title</th><th scope="col">Category</th><th scope="col" class="num">Size</th><th scope="col" class="num">Added</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
          <tbody>
            <?php foreach ($documents as $d): ?>
              <tr>
                <td data-label="Title"><a href="<?= e(url('/patient/documents/' . (int) $d['id'], profile_query($profile) + ['inline' => '1'])) ?>" target="_blank" rel="noopener"><?= e($d['title']) ?></a><span class="sub"><?= e($d['original_name']) ?></span></td>
                <td data-label="Category"><?= e($categories[$d['category']] ?? $d['category']) ?></td>
                <td data-label="Size" class="num"><?= e($size((int) $d['size_bytes'])) ?></td>
                <td data-label="Added" class="num"><?= e(fmt_date($d['created_at'], 'Y-m-d')) ?></td>
                <td data-label="Actions" class="cluster">
                  <a class="btn btn--ghost btn--small" href="<?= e(url('/patient/documents/' . (int) $d['id'], profile_query($profile))) ?>">Download</a>
                  <form method="post" action="<?= e(url('/patient/documents/' . (int) $d['id'] . '/delete')) ?>"><?= csrf_field() ?><?= profile_field($profile) ?><button class="btn btn--ghost btn--small" type="submit" aria-label="Delete <?= e($d['title']) ?>">Delete</button></form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <section class="panel span-4" aria-labelledby="doc-up">
    <div class="panel__head"><h2 id="doc-up">Upload</h2></div>
    <form method="post" action="<?= e(url('/patient/documents')) ?>" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?><?= profile_field($profile) ?>
      <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
      <div class="form-grid">
        <div class="field"><label for="f-title">Title</label><input class="input" id="f-title" name="title" maxlength="120" value="<?= e(old($old, 'title')) ?>"<?= aria_invalid($errors, 'title') ?>><?= field_error($errors, 'title') ?></div>
        <div class="field"><label for="f-category">Category</label>
          <select class="input" id="f-category" name="category"<?= aria_invalid($errors, 'category') ?>><option value="">Choose</option>
            <?php foreach ($categories as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'category'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
          </select><?= field_error($errors, 'category') ?></div>
        <div class="field"><label for="f-document">File</label><input class="input" type="file" id="f-document" name="document" accept="application/pdf,image/png,image/jpeg"<?= aria_invalid($errors, 'document') ?>><?= field_error($errors, 'document') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit" data-busy-label="Encrypting…">Upload</button></div>
    </form>
  </section>
</div>
