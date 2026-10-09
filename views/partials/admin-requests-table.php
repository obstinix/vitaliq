<?php
/** Admin list of requests. Expects $rows. */
use Vitaliq\Support\Labels;
?>
<div class="table-wrap">
  <table class="table table--stack">
    <thead>
      <tr>
        <th scope="col">Reference</th><th scope="col">Patient</th><th scope="col">Test</th><th scope="col">Submitted</th>
        <th scope="col">Preferred</th><th scope="col">Location</th><th scope="col">Phlebotomist</th><th scope="col">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td data-label="Reference"><a class="ref" href="<?= e(url('/admin/requests/' . (int) $r['id'])) ?>"><?= e($r['reference']) ?></a></td>
          <td data-label="Patient"><?= e($r['full_name']) ?><br><span class="mono small muted"><?= e(format_mobile($r['mobile'])) ?></span></td>
          <td data-label="Test"><?= e(Labels::get(Labels::TEST_TYPES_SHORT, $r['test_type'])) ?><br><span class="small muted"><?= e(ucfirst($r['collection_mode'])) ?></span></td>
          <td data-label="Submitted" class="nowrap"><?= e(fmt_date($r['submitted_at'], 'j M Y')) ?><br><span class="small muted"><?= e(fmt_date($r['submitted_at'], 'g:i a')) ?></span></td>
          <td data-label="Preferred" class="nowrap"><?= e(fmt_date($r['preferred_date'], 'j M')) ?> · <?= e(ucfirst($r['preferred_slot'])) ?></td>
          <td data-label="Location"><?= e($r['city']) ?>, <?= e($r['state_code']) ?></td>
          <td data-label="Phlebotomist"><?= $r['phlebotomist_name'] ? e($r['phlebotomist_name']) . '<br><span class="mono small muted">' . e($r['employee_code']) . '</span>' : '<span class="faint">—</span>' ?></td>
          <td data-label="Status">
            <span class="status status--<?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span>
            <?php if ($r['released_at'] !== null): ?><br><span class="small outcome outcome--<?= e($r['outcome']) ?>"><?= e(ucfirst($r['outcome'])) ?></span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
