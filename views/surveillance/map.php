<?php
/*
 * Question: "Where is positivity highest?"
 * Tile map: one square per state or union territory in its approximate
 * position; the list beside it carries the exact figures.
 */
$pos = [
    'JK' => [0, 2], 'LA' => [0, 3], 'CH' => [1, 0], 'PB' => [1, 1], 'HP' => [1, 2], 'UK' => [1, 3],
    'HR' => [2, 1], 'DL' => [2, 2], 'UP' => [2, 3], 'BR' => [2, 4], 'SK' => [2, 5], 'AS' => [2, 6], 'AR' => [2, 7],
    'RJ' => [3, 1], 'MP' => [3, 2], 'CG' => [3, 3], 'JH' => [3, 4], 'WB' => [3, 5], 'ML' => [3, 6], 'NL' => [3, 7],
    'GJ' => [4, 0], 'DH' => [4, 1], 'MH' => [4, 2], 'TG' => [4, 3], 'OD' => [4, 4], 'TR' => [4, 6], 'MN' => [4, 7],
    'GA' => [5, 1], 'KA' => [5, 2], 'AP' => [5, 3], 'MZ' => [5, 7],
    'LD' => [6, 0], 'KL' => [6, 2], 'TN' => [6, 3], 'PY' => [6, 4], 'AN' => [6, 6],
];
$levels = [0 => 'Fewer than 10 tests', 1 => 'Under 2%', 2 => '2–5%', 3 => '5–10%', 4 => '10% or more'];
$ranked = array_values(array_filter($tiles, static fn ($t) => $t['tests'] > 0));
usort($ranked, static fn ($a, $b) => ($b['rate'] ?? -1) <=> ($a['rate'] ?? -1) ?: $b['tests'] <=> $a['tests']);
?>
<div class="app-head">
  <div>
    <h1>Map by state</h1>
    <p><?= e($diseases[$disease]) ?> positivity, last <?= (int) $days ?> days. <?= count($ranked) ?> of <?= count($tiles) ?> states and union territories reported tests.</p>
  </div>
</div>
<?= $view->partial('surveillance-scope', ['base' => '/surveillance/map', 'disease' => $disease, 'days' => $days, 'state' => '', 'diseases' => $diseases, 'states' => $states, 'noState' => true]) ?>
<div class="vq-grid mt-4">
  <section class="panel span-7" aria-labelledby="tiles">
    <div class="panel__head"><h2 id="tiles">Positivity</h2></div>
    <ul class="legend" aria-label="Key">
      <?php foreach ($levels as $lv => $l): ?><li><span class="swatch" data-level="<?= $lv ?>"></span><?= e($l) ?></li><?php endforeach; ?>
    </ul>
    <div class="tilemap" role="list">
      <?php foreach ($tiles as $t): ?>
        <?php [$r, $c] = $pos[$t['code']] ?? [6, 7]; ?>
        <a role="listitem" class="r<?= $r ?> c<?= $c ?>" data-level="<?= (int) $t['level'] ?>" href="<?= e(url('/surveillance', ['disease' => $disease, 'days' => $days, 'state' => $t['code']])) ?>"
           title="<?= e($t['name'] . ': ' . ($t['rate'] === null ? $t['tests'] . ' tests' : number_format($t['rate'] * 100, 1) . '% of ' . $t['tests'] . ' tests')) ?>"
           aria-label="<?= e($t['name'] . ', ' . ($t['rate'] === null ? 'fewer than 10 tests' : number_format($t['rate'] * 100, 1) . ' percent positive of ' . $t['tests'] . ' tests')) ?>"><?= e($t['code']) ?></a>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="panel span-5" aria-labelledby="rank">
    <div class="panel__head"><h2 id="rank">States with tests</h2></div>
    <?php if ($ranked === []): ?>
      <p class="empty">No tests in this period.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th scope="col">State</th><th scope="col" class="num">Tests</th><th scope="col" class="num">Positive</th><th scope="col" class="num">Positivity</th></tr></thead>
        <tbody>
          <?php foreach ($ranked as $t): ?>
            <tr><td><?= e($t['name']) ?></td><td class="num"><?= fmt_number($t['tests']) ?></td><td class="num"><?= fmt_number($t['positives']) ?></td>
              <td class="num"><?= $t['rate'] === null ? '<span class="faint">—</span>' : e(number_format($t['rate'] * 100, 1)) . '%' ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
