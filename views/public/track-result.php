<section class="wrap section">
  <p class="eyebrow"><span class="num">Track</span> Request status</p>
  <h1 class="mb-5"><span class="ref"><?= e($request['reference']) ?></span></h1>
  <div class="split split--even">
    <div class="stack">
      <?= $view->partial('status-rail', ['request' => $request]) ?>
      <?= $view->partial('result-panel', ['request' => $request]) ?>
    </div>
    <div class="stack">
      <div class="panel">
        <?= $view->partial('request-summary', ['request' => $request]) ?>
      </div>
      <details class="confirm">
        <summary>Full status history</summary>
        <div class="confirm__body"><?= $view->partial('history', ['history' => $request['history']]) ?></div>
      </details>
      <p class="hint">To cancel this request, sign in after linking it to an account, or contact the helpdesk with your reference.</p>
    </div>
  </div>
</section>
