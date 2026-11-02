<div class="app-head">
  <div>
    <h1>Backups</h1>
    <p>Encrypted, compressed copies of the whole database. The newest <?= (int) $keep ?> are kept. A backup can be decrypted only with this installation's APP_KEY, and restores only into an empty database from the command line.</p>
  </div>
  <form method="post" action="<?= e(url('/admin/backups')) ?>"><?= csrf_field() ?><button class="btn btn--primary" type="submit" data-busy-label="Writing…">Back up now</button></form>
</div>
<section class="panel">
  <?php if ($rows === []): ?>
    <p class="empty">No backups yet. Schedule <span class="ref">php bin/backup.php</span> daily, or back up now.</p>
  <?php else: ?>
    <table class="table table--stack">
      <thead><tr><th scope="col">File</th><th scope="col" class="num">Tables</th><th scope="col" class="num">Rows</th><th scope="col" class="num">Size</th><th scope="col" class="num">Written</th><th scope="col"><span class="visually-hidden">Download</span></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $b): ?>
          <tr>
            <td data-label="File"><span class="ref"><?= e($b['filename']) ?></span><span class="sub">SHA-256 <?= e(substr((string) $b['sha256'], 0, 12)) ?>… · <?= e($b['full_name'] ?? 'scheduled job') ?></span></td>
            <td data-label="Tables" class="num"><?= (int) $b['tables_count'] ?></td>
            <td data-label="Rows" class="num"><?= fmt_number($b['rows_count']) ?></td>
            <td data-label="Size" class="num"><?= e(number_format((int) $b['size_bytes'] / 1024, 0)) ?> KB</td>
            <td data-label="Written" class="num"><?= e(fmt_datetime($b['created_at'])) ?></td>
            <td data-label="Download"><a class="btn btn--ghost btn--small" href="<?= e(url('/admin/backups/' . (int) $b['id'])) ?>">Download</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <p class="hint mt-4">To restore: <span class="ref">php bin/backup-restore.php &lt;file&gt; --into=&lt;empty database&gt;</span>. Restoring never overwrites a database that already has tables.</p>
</section>
