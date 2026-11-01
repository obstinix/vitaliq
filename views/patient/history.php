<div class="app-head">
  <div>
    <h1>Tests and results</h1>
    <p><?= count($requests) ?> testing <?= count($requests) === 1 ? 'request' : 'requests' ?> and <?= count($labOrders) ?> released lab <?= count($labOrders) === 1 ? 'report' : 'reports' ?>, newest first.</p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('/patient/requests/new')) ?>">Request a test</a>
</div>
<section class="panel" aria-labelledby="req-title">
  <div class="panel__head"><h2 id="req-title">Testing requests</h2></div>
  <?php if ($requests === []): ?>
    <p class="empty">No requests yet. Your requests, their status and authorised results will be listed here.</p>
  <?php else: ?>
    <?= $view->partial('requests-table', ['rows' => $requests]) ?>
  <?php endif; ?>
</section>
<section class="panel" aria-labelledby="lab-title">
  <div class="panel__head"><h2 id="lab-title">Laboratory reports</h2></div>
  <?php if ($labOrders === []): ?>
    <p class="empty">No laboratory reports have been released to you yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead><tr><th scope="col">Order</th><th scope="col">For</th><th scope="col" class="num">Tests</th><th scope="col" class="num">Outside range</th><th scope="col" class="num">Released</th></tr></thead>
        <tbody>
          <?php foreach ($labOrders as $o): ?>
            <tr>
              <td data-label="Order"><a class="ref" href="<?= e(url('/patient/lab-results/' . (int) $o['id'])) ?>"><?= e($o['order_no']) ?></a></td>
              <td data-label="For"><?= e($o['full_name']) ?></td>
              <td data-label="Tests" class="num"><?= (int) $o['tests'] ?></td>
              <td data-label="Outside range" class="num"><?= (int) $o['flagged'] ?></td>
              <td data-label="Released" class="num"><?= e(fmt_date($o['released_at'], 'Y-m-d')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
