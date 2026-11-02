<section class="section">
  <div class="wrap wrap--narrow">
    <h1>Verify a report</h1>
    <p class="lead">Enter the 16-character code printed on a Vitaliq report, or scan its QR code.</p>
    <form method="post" action="<?= e(url('/verify')) ?>" class="cluster">
      <?= csrf_field() ?>
      <label class="visually-hidden" for="f-code">Verification code</label>
      <input class="input input--mono" id="f-code" name="code" maxlength="24" placeholder="ABCD-EFGH-JKLM-NPQR" value="<?= e($code) ?>">
      <button class="btn btn--primary" type="submit">Verify</button>
    </form>
    <?php if ($result !== null): ?>
      <?php if (!empty($result['valid'])): ?>
        <div class="callout callout--success mt-4" role="status">
          <h2>Genuine report</h2>
          <dl class="details">
            <dt>Reference</dt><dd class="ref"><?= e($result['reference']) ?></dd>
            <dt>Test</dt><dd><?= e($result['test']) ?></dd>
            <dt>Result</dt><dd><?= e($result['outcome']) ?></dd>
            <dt>Issued</dt><dd><?= e(fmt_datetime($result['issued'])) ?></dd>
            <dt>Person</dt><dd><?= e($result['initials']) ?>, born <?= e($result['birth_year']) ?></dd>
          </dl>
          <p class="small">Check that these details match the printed report. Checked <?= (int) $result['checks'] ?> <?= (int) $result['checks'] === 1 ? 'time' : 'times' ?>.</p>
        </div>
      <?php else: ?>
        <div class="callout callout--urgent mt-4" role="alert"><h2>Not verified</h2><p><?= e($result['reason'] ?? 'This code is not valid.') ?></p></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
