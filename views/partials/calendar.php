<?php
/**
 * Month grid. Expects $grid (AppointmentService::monthGrid), $base (page
 * path), $query (extra query for the month links) and $link (event URL prefix).
 */
use Vitaliq\Services\AppointmentService;

$query = $query ?? [];
?>
<section class="panel" aria-labelledby="cal-title">
  <div class="panel__head">
    <h2 id="cal-title"><?= e($grid['label']) ?></h2>
    <div class="cluster">
      <a class="btn btn--secondary btn--small" href="<?= e(url($base, $query + ['month' => $grid['prev']])) ?>" aria-label="Previous month">Previous</a>
      <a class="btn btn--secondary btn--small" href="<?= e(url($base, $query + ['month' => $grid['next']])) ?>" aria-label="Next month">Next</a>
    </div>
  </div>
  <div class="cal" role="grid" aria-labelledby="cal-title">
    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow): ?>
      <div class="dow" role="columnheader"><?= e($dow) ?></div>
    <?php endforeach; ?>
    <?php foreach ($grid['weeks'] as $week): ?>
      <?php foreach ($week as $day): ?>
        <div role="gridcell" class="<?= $day['in'] ? '' : 'out' ?><?= $day['weekend'] && $day['in'] ? ' weekend' : '' ?><?= $day['today'] ? ' today' : '' ?>"<?= $day['today'] ? ' aria-current="date"' : '' ?>>
          <span class="day-n"><?= (int) substr($day['date'], 8, 2) ?></span>
          <?php foreach (array_slice($day['events'], 0, 3) as $ev): ?>
            <a class="ev<?= in_array($ev['status'], ['cancelled', 'missed'], true) ? ' ev--cancelled' : '' ?>" href="<?= e(!empty($linkDay) ? url($link, ['day' => substr((string) $ev['starts_at'], 0, 10)]) : url($link . (int) $ev['id'])) ?>" title="<?= e(AppointmentService::KINDS[$ev['kind']] . ', ' . $ev['full_name']) ?>">
              <?= e(fmt_date($ev['starts_at'], 'H:i')) ?> <?= e(AppointmentService::KINDS[$ev['kind']]) ?>
            </a>
          <?php endforeach; ?>
          <?php if (count($day['events']) > 3): ?><span class="sub">+<?= count($day['events']) - 3 ?> more</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</section>
