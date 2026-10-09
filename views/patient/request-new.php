<div class="app-head">
  <div>
    <p class="eyebrow">Existing patient</p>
    <h1>Request a test</h1>
    <p>Your personal details come from your profile, so only the test details are needed.</p>
  </div>
</div>

<div class="grid-2">
  <div>
    <?= $view->partial('error-summary', ['errors' => $errors]) ?>
    <form class="form" method="post" action="<?= e(url('/patient/requests/new')) ?>" novalidate data-validate data-once>
      <?= csrf_field() ?>
      <input type="hidden" name="form_token" value="<?= e($formToken) ?>">
      <?= $view->partial('test-fields', compact('old', 'errors', 'states', 'today', 'maxDate') + ['n' => 1]) ?>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit" data-busy-label="Submitting…">Submit request</button>
        <a href="<?= e(url('/patient')) ?>">Cancel</a>
      </div>
    </form>
  </div>
  <aside class="stack">
    <?php if ($patient !== null): ?>
      <section class="panel" aria-labelledby="for-title">
        <div class="panel__head"><h2 id="for-title">This request is for</h2><a href="<?= e(url('/account')) ?>">Edit details</a></div>
        <dl class="details">
          <dt>Name</dt><dd><?= e($patient['full_name']) ?></dd>
          <dt>Date of birth</dt><dd><?= e(fmt_date($patient['date_of_birth'])) ?></dd>
          <dt>Mobile</dt><dd class="mono"><?= e(format_mobile($patient['mobile'])) ?></dd>
        </dl>
      </section>
    <?php endif; ?>
    <div class="callout callout--note"><p><strong>Demonstration system.</strong> No real sample will be collected.</p></div>
  </aside>
</div>
