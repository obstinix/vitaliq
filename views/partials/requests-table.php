<?php
/** Patient-facing list of requests. Expects $rows. */
use Vitaliq\Support\Labels;
?>
<div class="table-wrap">
  <table class="table table--stack">
    <thead>
      <tr><th scope="col">Reference</th><th scope="col">Test</th><th scope="col">Submitted</th><th scope="col">Preferred</th><th scope="col">Status</th><th scope="col">Result</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td data-label="Reference"><a class="ref" href="<?= e(url('/patient/requests/' . (int) $r['id'])) ?>"><?= e($r['reference']) ?></a></td>
          <td data-label="Test"><?= e(Labels::get(Labels::TEST_TYPES_SHORT, $r['test_type'])) ?></td>
          <td data-label="Submitted"><?= e(fmt_date($r['submitted_at'])) ?></td>
          <td data-label="Preferred"><?= e(fmt_date($r['preferred_date'], 'j M')) ?> · <?= e(ucfirst($r['preferred_slot'])) ?></td>
          <td data-label="Status"><span class="status status--<?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></td>
          <td data-label="Result">
            <?php if ($r['status'] === 'result_available'): ?>
              <span class="outcome outcome--<?= e($r['outcome']) ?>"><?= e(Labels::get(Labels::OUTCOMES, $r['outcome'])) ?></span>
            <?php else: ?>
              <span class="faint">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
