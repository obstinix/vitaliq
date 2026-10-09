<?php
/**
 * Script tags. GSAP is loaded only where $motion is true (public pages);
 * D3 only when a page lists 'charts' in $scripts.
 */
$scripts = $scripts ?? [];
?>
<?= json_script('vq-rules', $clientRules ?? []) ?>
<?php if (!empty($motion)): ?>
<script src="<?= e(asset('vendor/gsap-3.15.0.min.js')) ?>" defer></script>
<script src="<?= e(asset('vendor/ScrollTrigger-3.15.0.min.js')) ?>" defer></script>
<?php endif; ?>
<?php if (in_array('charts', $scripts, true)): ?>
<script src="<?= e(asset('vendor/d3-vitaliq-7.min.js')) ?>" defer></script>
<?php endif; ?>
<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script type="module" src="<?= e(asset('js/' . $script . '.js')) ?>"></script>
<?php endforeach; ?>
