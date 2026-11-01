<?php
$kinds = ['request' => 'Request', 'sample' => 'Sample', 'result' => 'Result', 'appointment' => 'Appointment', 'immunisation' => 'Immunisation', 'document' => 'Document'];
$byYear = [];
foreach ($events as $ev) {
    $byYear[substr((string) $ev['at'], 0, 4)][] = $ev;
}
?>
<div class="app-head">
  <div>
    <h1>Health timeline</h1>
    <p><?= count($events) ?> <?= count($events) === 1 ? 'event' : 'events' ?> on <?= $profile['rel'] === 'self' ? 'your record' : e($profile['full_name']) . "'s record" ?>, newest first.</p>
  </div>
</div>
<?= $view->partial('profile-switch', ['profiles' => $profiles, 'profile' => $profile, 'base' => '/patient/timeline']) ?>
<?php if ($events === []): ?>
  <p class="empty">Nothing on this record yet. Requests, results, appointments, immunisations and documents appear here as they happen.</p>
<?php else: ?>
  <?php foreach ($byYear as $year => $items): ?>
    <section class="panel" aria-labelledby="y-<?= e((string) $year) ?>">
      <div class="panel__head"><h2 id="y-<?= e((string) $year) ?>"><?= e((string) $year) ?></h2><span class="meta"><?= count($items) ?> events</span></div>
      <ol class="timeline">
        <?php foreach ($items as $ev): ?>
          <li>
            <time datetime="<?= e(iso_datetime($ev['at'])) ?>"><?= e($ev['kind'] === 'immunisation' ? fmt_date($ev['at']) : fmt_datetime($ev['at'])) ?></time>
            <strong><?= e($ev['title']) ?></strong> <span class="tag"><?= e($kinds[$ev['kind']] ?? $ev['kind']) ?></span>
            <div class="who"><?php if ($ev['link'] !== null): ?><a href="<?= e(url($ev['link'])) ?>"><?= e($ev['detail']) ?></a><?php else: ?><?= e($ev['detail']) ?><?php endif; ?></div>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
