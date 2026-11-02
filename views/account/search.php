<?php
$total = array_sum(array_map('count', $groups));
?>
<div class="app-head">
  <div>
    <h1>Search</h1>
    <p><?php if ($limited): ?>Too many searches in a minute. Please wait a moment.<?php elseif (mb_strlen(trim($q)) < 2): ?>Type at least two characters in the search box above.<?php else: ?><?= $total ?> <?= $total === 1 ? 'match' : 'matches' ?> for “<?= e($q) ?>” in what your role can open.<?php endif; ?></p>
  </div>
</div>
<form class="cluster mb-0" method="get" action="<?= e(url('/search')) ?>" role="search">
  <label class="visually-hidden" for="sq">Search</label>
  <input class="input" id="sq" name="q" type="search" value="<?= e($q) ?>" maxlength="80">
  <button class="btn btn--secondary" type="submit">Search</button>
</form>
<?php if ($groups === [] && mb_strlen(trim($q)) >= 2 && !$limited): ?>
  <p class="empty mt-4">Nothing matches. Try a reference, a barcode, part of a name or a mobile number.</p>
<?php endif; ?>
<div class="vq-grid mt-4">
  <?php foreach ($groups as $label => $rows): ?>
    <section class="panel span-6" aria-label="<?= e($label) ?>">
      <div class="panel__head"><h2><?= e($label) ?></h2><span class="meta"><?= count($rows) ?></span></div>
      <ul class="rows">
        <?php foreach ($rows as $r): ?><li><span class="grow"><a href="<?= e(url($r['url'])) ?>"><?= e($r['label']) ?></a><span class="sub"><?= e($r['detail']) ?></span></span></li><?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
</div>
