<?php
/**
 * Released result for patients. Renders nothing unless the request has
 * reached result_available. Expects $request; optional $reportUrl.
 */
use Vitaliq\Support\Labels;

if ($request['status'] !== 'result_available' || empty($request['result'])) {
    return;
}
$r = $request['result'];
?>
<section class="callout <?= $r['outcome'] === 'positive' ? 'callout--urgent' : 'callout--success' ?>" aria-labelledby="result-title">
  <h2 id="result-title">Your result</h2>
  <p class="outcome outcome--<?= e($r['outcome']) ?>"><?= e(Labels::get(Labels::OUTCOMES, $r['outcome'])) ?></p>
  <p>Released <?= e(fmt_datetime($r['released_at'])) ?>.<?= $r['remarks'] ? ' ' . e($r['remarks']) : '' ?></p>
  <?php if ($r['outcome'] === 'positive'): ?>
    <p>Stay away from others, especially anyone at higher risk, and contact a clinician if you are at higher risk or your symptoms get worse. For emergency warning signs, call <strong>112</strong>.</p>
  <?php elseif ($r['outcome'] === 'negative' && $request['test_type'] === 'rapid_antigen'): ?>
    <p>A single negative antigen test does not rule out infection. If you have symptoms, test again in 48 hours. <a href="<?= e(url('/testing')) ?>">Repeat-testing guidance</a>.</p>
  <?php elseif (in_array($r['outcome'], ['inconclusive', 'invalid'], true)): ?>
    <p>This result cannot confirm or rule out infection. Please request a new test.</p>
  <?php endif; ?>
  <?php if (!empty($reportUrl)): ?>
    <a class="btn btn--secondary btn--small" href="<?= e($reportUrl) ?>">Open printable report</a>
  <?php endif; ?>
</section>
