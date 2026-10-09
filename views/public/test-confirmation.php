<section class="wrap section">
  <div class="split">
    <div>
      <p class="eyebrow"><span class="num">✓</span> Request received</p>
      <h1>Thank you. Your request is in.</h1>
      <p class="lede">Keep this reference. You need it, with the mobile number and date of birth you gave, to track the request.</p>
      <p class="mt-5"><span class="ref ref--big" data-copy><?= e($request['reference']) ?></span></p>
      <div class="cluster mt-5">
        <a class="btn btn--primary" href="<?= e(url('/track/' . $request['reference'])) ?>">Track this request</a>
        <a class="btn btn--secondary" href="<?= e(url('/register')) ?>">Create an account</a>
      </div>
      <p class="hint mt-4">With an account you can see every request in one place and receive in-app notifications. Use “Link a request” after registering to add this one.</p>
    </div>
    <div class="stack">
      <?= $view->partial('status-rail', ['request' => $request]) ?>
      <div class="panel">
        <?= $view->partial('request-summary', ['request' => $request]) ?>
      </div>
    </div>
  </div>
</section>
