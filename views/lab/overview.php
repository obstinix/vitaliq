<?php
use Vitaliq\Services\LaboratoryService;

/*
 * Question: "What does the bench need to do next?"
 * Biggest block: the work queue by section; verification and overdue orders beside it.
 */
$w = $workload;
$c = $samples['counts'];
$toEnter = array_sum(array_column($w['sections'], 'to_enter'));
$toVerify = array_sum(array_column($w['sections'], 'to_verify'));
?>
<div class="app-head">
  <div>
    <h1>Laboratory</h1>
    <p><?= $toEnter ?> results to enter · <?= $verifyTotal ?> to verify · <?= count($w['overdue']) ?> orders past their turnaround time.</p>
  </div>
  <div class="cluster">
    <a class="btn btn--secondary" href="<?= e(url('/lab/scan')) ?>">Scan a barcode</a>
    <?php if (app()->can('lab.orders')): ?><a class="btn btn--primary" href="<?= e(url('/lab/orders/new')) ?>">New test order</a><?php endif; ?>
  </div>
</div>

<nav class="pipeline" aria-label="Samples by stage">
  <?php foreach (['awaiting_collection', 'collected', 'in_transit', 'received', 'in_analysis', 'stored', 'rejected'] as $st): ?>
    <a href="<?= e(url('/lab/samples', ['status' => $st])) ?>"><span class="n"><?= (int) $c[$st] ?></span><span class="l"><?= e(LaboratoryService::SAMPLE_STATUSES[$st]) ?></span></a>
  <?php endforeach; ?>
</nav>

<div class="vq-grid">
  <div class="span-8 stack">
    <section class="panel" aria-labelledby="q-title">
      <div class="panel__head"><h2 id="q-title">Work queue by section</h2><span class="meta">Open orders only</span></div>
      <?php if ($w['sections'] === []): ?>
        <p class="empty">No open orders. New orders appear here once their samples are labelled.</p>
      <?php else: ?>
        <table class="table">
          <thead><tr><th scope="col">Section</th><th scope="col" class="num">Waiting for sample</th><th scope="col" class="num">To enter</th><th scope="col" class="num">To verify</th></tr></thead>
          <tbody>
            <?php foreach ($w['sections'] as $s): ?>
              <tr><td><?= e(LaboratoryService::SECTIONS[$s['section']]) ?></td><td class="num"><?= $s['waiting_sample'] ?></td><td class="num"><?= $s['to_enter'] ?></td><td class="num"><?= $s['to_verify'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="od-title">
      <div class="panel__head"><h2 id="od-title">Past turnaround time</h2><a href="<?= e(url('/lab/orders')) ?>">All orders</a></div>
      <?php if ($w['overdue'] === []): ?>
        <p class="empty">Every open order is within its turnaround time.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach (array_slice($w['overdue'], 0, 6) as $o): ?>
            <li><span class="grow"><a class="ref" href="<?= e(url('/lab/orders/' . (int) $o['id'])) ?>"><?= e($o['order_no']) ?></a><span class="sub"><?= e($o['full_name']) ?> · <?= e(LaboratoryService::PRIORITIES[$o['priority']]) ?></span></span>
              <span class="end num">ordered <?= e(fmt_datetime($o['created_at'])) ?><span class="sub">target <?= (int) $o['tat'] ?> h</span></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if ($w['covid_awaiting'] > 0): ?>
      <p class="callout callout--note"><?= (int) $w['covid_awaiting'] ?> COVID-19 testing <?= $w['covid_awaiting'] === 1 ? 'request is' : 'requests are' ?> in the laboratory stage of the testing workflow. <?php if (app()->auth()->is('admin')): ?><a href="<?= e(url('/admin/requests', ['status' => 'sample_collected'])) ?>">Open them</a>.<?php endif; ?></p>
    <?php endif; ?>
  </div>

  <div class="span-4 stack">
    <section class="panel" aria-labelledby="v-title">
      <div class="panel__head"><h2 id="v-title">Awaiting verification</h2><a href="<?= e(url('/lab/verify')) ?>">All <?= (int) $verifyTotal ?></a></div>
      <?php if ($verify === []): ?>
        <p class="empty">Nothing waiting for a second check.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($verify as $i): ?>
            <li><span class="grow"><?= e($i['name']) ?><span class="sub"><?= e($i['order_no']) ?> · <?= e($i['full_name']) ?></span></span>
              <span class="end"><span class="flag flag--<?= e(str_starts_with((string) $i['flag'], 'critical') ? 'critical' : (string) $i['flag']) ?>"><?= e(LaboratoryService::FLAGS[$i['flag']] ?? '') ?></span></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="st-title">
      <div class="panel__head"><h2 id="st-title">Stock and equipment</h2></div>
      <?php if ($stock === [] && $equipment === []): ?>
        <p class="empty">Stock is above reorder levels and equipment is in calibration.</p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach (array_slice($stock, 0, 4) as $s): ?>
            <li><span class="grow"><a href="<?= e(url('/lab/inventory/' . (int) $s['id'])) ?>"><?= e($s['name']) ?></a><span class="sub"><?= (int) $s['usable'] ?> <?= e($s['unit']) ?> usable</span></span>
              <span class="status status--<?= e($s['state'] === 'out' ? 'critical' : 'low') ?>"><?= e(['out' => 'Out of stock', 'low' => 'Low', 'expiring' => 'Expiring'][$s['state']]) ?></span></li>
          <?php endforeach; ?>
          <?php foreach (array_slice($equipment, 0, 3) as $e): ?>
            <li><span class="grow"><a href="<?= e(url('/lab/equipment/' . (int) $e['id'])) ?>"><?= e($e['name']) ?></a><span class="sub"><?= e($e['asset_tag']) ?></span></span>
              <span class="status status--<?= e($e['status'] !== 'operational' ? 'out_of_service' : 'due') ?>"><?= e($e['status'] !== 'operational' ? 'Not in use' : ($e['calibration_state'] === 'overdue' ? 'Calibration overdue' : 'Calibration due')) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
