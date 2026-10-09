<?php $cancellable = in_array($request['status'], ['submitted', 'assigned'], true); ?>
<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/patient/requests')) ?>">Testing history</a></p>
    <h1><span class="ref"><?= e($request['reference']) ?></span></h1>
    <p><span class="status status--<?= e($request['status']) ?>"><?= e(status_label($request['status'])) ?></span></p>
  </div>
</div>

<div class="grid-2">
  <div class="stack">
    <?= $view->partial('result-panel', ['request' => $request, 'reportUrl' => url('/patient/requests/' . (int) $request['id'] . '/report')]) ?>
    <section class="panel" aria-labelledby="details-title">
      <div class="panel__head"><h2 id="details-title">Request details</h2></div>
      <?= $view->partial('request-summary', ['request' => $request]) ?>
    </section>
    <section class="panel" aria-labelledby="history-title">
      <div class="panel__head"><h2 id="history-title">History</h2></div>
      <?= $view->partial('history', ['history' => $request['history']]) ?>
    </section>
  </div>
  <div class="stack">
    <?= $view->partial('status-rail', ['request' => $request]) ?>
    <?php if ($cancellable): ?>
      <details class="confirm confirm--danger">
        <summary>Cancel this request</summary>
        <div class="confirm__body">
          <form method="post" action="<?= e(url('/patient/requests/' . (int) $request['id'] . '/cancel')) ?>" class="stack" data-once>
            <?= csrf_field() ?>
            <div class="field">
              <label for="f-reason">Reason <span class="optional">(optional)</span></label>
              <input class="input" id="f-reason" name="reason" maxlength="300">
            </div>
            <button class="btn btn--danger btn--small" type="submit">Yes, cancel request</button>
          </form>
          <p class="hint mt-4">You can cancel until the sample is collected.</p>
        </div>
      </details>
    <?php endif; ?>
  </div>
</div>
