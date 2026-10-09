<?php
/** Source box for information pages. Expects $sources: list of [title, url, publisher, updated]. */
?>
<aside class="sources-box" aria-labelledby="sources-heading">
  <h2 id="sources-heading">Sources</h2>
  <ul class="source-list">
    <?php foreach ($sources as [$title, $href, $publisher, $updated]): ?>
      <li>
        <a href="<?= e($href) ?>" rel="noopener" target="_blank"><?= e($title) ?><span class="visually-hidden"> (opens in a new tab)</span></a>
        <span class="source-meta"><?= e($publisher) ?><?= $updated ? ' · page last updated ' . e($updated) : '' ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="reviewed mt-4 mb-0">Vitaliq summary checked against these sources on 10 Oct 2026. Guidance changes; follow your local health authority and clinician.</p>
</aside>
