<?php use Vitaliq\Services\AnnouncementService; ?>
<header class="page-head">
  <div class="wrap">
    <p class="eyebrow"><span class="num">News</span> Announcements</p>
    <h1>Public-health updates and service notices.</h1>
    <p class="lede">Public-health items always cite their source. Service notices describe this Vitaliq deployment; those marked “Demo notice” are synthetic examples.</p>
  </div>
</header>
<div class="wrap section">
  <?php if ($announcements === []): ?>
    <div class="empty"><h2>Nothing published yet</h2><p>Announcements will appear here with their date and source.</p></div>
  <?php else: ?>
    <ul class="notice-list">
      <?php foreach ($announcements as $a): ?>
        <li data-reveal>
          <time class="notice-date" datetime="<?= e($a['published_on']) ?>"><?= e(fmt_date($a['published_on'])) ?></time>
          <div>
            <p class="eyebrow eyebrow--plain mb-0"><?= e(AnnouncementService::CATEGORIES[$a['category']] ?? '') ?></p>
            <h2 class="h3-like"><?= e($a['title']) ?></h2>
            <p><?= e($a['summary']) ?></p>
            <div class="cluster">
              <?php if ($a['source_url']): ?>
                <a href="<?= e($a['source_url']) ?>" rel="noopener" target="_blank">Source: <?= e($a['source_name']) ?><span class="visually-hidden"> (opens in a new tab)</span></a>
              <?php endif; ?>
              <?php if ((int) $a['is_demo'] === 1): ?><span class="tag tag--demo">Demo notice</span><?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
