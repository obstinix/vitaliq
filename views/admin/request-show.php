<?php
use Vitaliq\Services\PatientService;
use Vitaliq\Support\Labels;

$r = $request;
$s = $r['status'];
$age = PatientService::age($r['date_of_birth'], new DateTimeImmutable($r['submitted_at']));
?>
<div class="app-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('/admin/requests', ['status' => $s])) ?>">Requests</a></p>
    <h1><span class="ref"><?= e($r['reference']) ?></span></h1>
    <p><span class="status status--<?= e($s) ?>"><?= e(status_label($s)) ?></span> · <?= e(Labels::get(Labels::TEST_TYPES, $r['test_type'])) ?> · submitted <?= e(fmt_datetime($r['submitted_at'])) ?> via <?= $r['channel'] === 'guest' ? 'guest form' : 'patient account' ?></p>
  </div>
</div>

<div class="grid-2">
  <div class="stack">
    <?php /* ---------- Actions for the current status ---------- */ ?>
    <?php if (in_array($s, ['submitted', 'assigned'], true)): ?>
      <section class="panel" aria-labelledby="assign-title">
        <div class="panel__head"><h2 id="assign-title"><?= $s === 'submitted' ? 'Assign a phlebotomist' : 'Reassign' ?></h2></div>
        <?php if ($phlebotomists === []): ?>
          <p class="mb-0">No active phlebotomists. <a href="<?= e(url('/admin/phlebotomists/new')) ?>">Add one</a> first.</p>
        <?php else: ?>
          <form class="form-grid form-grid--2" method="post" action="<?= e(url('/admin/requests/' . (int) $r['id'] . '/assign')) ?>" data-once>
            <?= csrf_field() ?>
            <div class="field span-2">
              <label for="f-phlebotomist_id">Phlebotomist</label>
              <select class="input" id="f-phlebotomist_id" name="phlebotomist_id" required>
                <option value="">Choose (sorted by city)</option>
                <?php foreach ($phlebotomists as $id => $label): ?>
                  <option value="<?= (int) $id ?>"<?= selected(old($old, 'phlebotomist_id'), (string) $id) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="hint">Request location: <?= e($r['city']) ?>, <?= e($r['state_name']) ?> <?= e($r['pincode']) ?>.</p>
            </div>
            <div class="field span-2">
              <label for="f-note">Note <span class="optional">(optional)</span></label>
              <input class="input" id="f-note" name="note" maxlength="300">
            </div>
            <div><button class="btn btn--primary" type="submit"><?= $s === 'submitted' ? 'Assign' : 'Reassign' ?></button></div>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($s === 'assigned'): ?>
      <section class="panel" aria-labelledby="collect-title">
        <div class="panel__head"><h2 id="collect-title">Record collection on the phlebotomist’s behalf</h2></div>
        <?= $view->partial('collect-form', ['action' => url('/admin/requests/' . (int) $r['id'] . '/collect'), 'errors' => $errors, 'old' => $old]) ?>
      </section>
    <?php endif; ?>

    <?php if ($s === 'sample_collected'): ?>
      <section class="panel" aria-labelledby="lab-title">
        <div class="panel__head"><h2 id="lab-title">Laboratory intake</h2></div>
        <form class="stack" method="post" action="<?= e(url('/admin/requests/' . (int) $r['id'] . '/process')) ?>" data-once>
          <?= csrf_field() ?>
          <p class="mb-0">Confirm the sample (tube <span class="mono"><?= e($r['sample']['barcode'] ?? '') ?></span>) has arrived and testing has started.</p>
          <div class="field">
            <label for="f-lab-note">Note <span class="optional">(optional)</span></label>
            <input class="input" id="f-lab-note" name="note" maxlength="300">
          </div>
          <div><button class="btn btn--primary" type="submit">Mark testing in progress</button></div>
        </form>
      </section>
    <?php endif; ?>

    <?php if ($s === 'in_progress'): ?>
      <section class="panel" aria-labelledby="result-title">
        <div class="panel__head"><h2 id="result-title">Record result</h2></div>
        <form class="form-grid form-grid--2" method="post" action="<?= e(url('/admin/requests/' . (int) $r['id'] . '/result')) ?>" novalidate data-validate data-once>
          <?= csrf_field() ?>
          <div class="field">
            <label for="f-outcome">Result</label>
            <select class="input" id="f-outcome" name="outcome" required<?= aria_invalid($errors, 'outcome') ?>>
              <option value="">Choose</option>
              <?php foreach (Labels::OUTCOMES as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= selected(old($old, 'outcome'), $key) ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'outcome') ?>
          </div>
          <?php if ($r['test_type'] === 'rt_pcr'): ?>
            <div class="field">
              <label for="f-ct_value">Ct value <span class="optional">(optional)</span></label>
              <input class="input input--mono" id="f-ct_value" name="ct_value" inputmode="decimal" maxlength="4" data-rule="ct_value" data-optional value="<?= e(old($old, 'ct_value')) ?>"<?= aria_invalid($errors, 'ct_value') ?>>
              <?= field_error($errors, 'ct_value') ?>
            </div>
          <?php endif; ?>
          <div class="field span-2">
            <label for="f-remarks">Remarks for the patient <span class="optional">(optional)</span></label>
            <textarea class="input" id="f-remarks" name="remarks" rows="2" maxlength="500"<?= aria_invalid($errors, 'remarks') ?>><?= e(old($old, 'remarks')) ?></textarea>
            <?= field_error($errors, 'remarks') ?>
          </div>
          <div class="span-2"><button class="btn btn--primary" type="submit">Save result</button> <span class="hint">The patient sees it only after you release it.</span></div>
        </form>
      </section>
    <?php endif; ?>

    <?php if ($s === 'completed' && $r['result']): ?>
      <section class="panel" aria-labelledby="release-title">
        <div class="panel__head"><h2 id="release-title">Authorise and release</h2></div>
        <p>Recorded result: <span class="outcome outcome--<?= e($r['result']['outcome']) ?>"><?= e(Labels::get(Labels::OUTCOMES, $r['result']['outcome'])) ?></span><?= $r['result']['ct_value'] !== null ? ' · Ct ' . e($r['result']['ct_value']) : '' ?>, by <?= e($r['result']['recorded_by_name'] ?? 'unknown') ?> at <?= e(fmt_datetime($r['result']['recorded_at'])) ?>.</p>
        <form method="post" action="<?= e(url('/admin/requests/' . (int) $r['id'] . '/release')) ?>" data-once>
          <?= csrf_field() ?>
          <button class="btn btn--primary" type="submit">Release result to patient</button>
        </form>
      </section>
    <?php endif; ?>

    <?php if ($s === 'result_available' && $r['result']): ?>
      <section class="panel" aria-labelledby="released-title">
        <div class="panel__head"><h2 id="released-title">Released result</h2></div>
        <dl class="details">
          <dt>Outcome</dt><dd><span class="outcome outcome--<?= e($r['result']['outcome']) ?>"><?= e(Labels::get(Labels::OUTCOMES, $r['result']['outcome'])) ?></span></dd>
          <?php if ($r['result']['ct_value'] !== null): ?><dt>Ct value</dt><dd><?= e($r['result']['ct_value']) ?></dd><?php endif; ?>
          <dt>Recorded</dt><dd><?= e(fmt_datetime($r['result']['recorded_at'])) ?> by <?= e($r['result']['recorded_by_name'] ?? '—') ?></dd>
          <dt>Released</dt><dd><?= e(fmt_datetime($r['result']['released_at'])) ?> by <?= e($r['result']['released_by_name'] ?? '—') ?></dd>
          <?php if ($r['result']['remarks']): ?><dt>Remarks</dt><dd><?= e($r['result']['remarks']) ?></dd><?php endif; ?>
        </dl>
      </section>
    <?php endif; ?>

    <?php if (in_array($s, ['cancelled', 'rejected'], true)): ?>
      <div class="callout callout--note"><p><strong><?= e(status_label($s)) ?>.</strong> <?= e($r['closed_reason'] ?? '') ?></p></div>
    <?php endif; ?>

    <section class="panel" aria-labelledby="history-title">
      <div class="panel__head"><h2 id="history-title">Status history</h2></div>
      <?= $view->partial('history', ['history' => $r['history'], 'showActors' => true]) ?>
    </section>
  </div>

  <div class="stack">
    <section class="panel" aria-labelledby="patient-title">
      <div class="panel__head"><h2 id="patient-title">Patient</h2></div>
      <dl class="details">
        <dt>Name</dt><dd><?= e($r['full_name']) ?></dd>
        <dt>Age / sex</dt><dd><?= (int) $age ?> · <?= e(Labels::get(Labels::SEX, $r['sex'])) ?></dd>
        <dt>Mobile</dt><dd class="mono"><?= e(format_mobile($r['mobile'])) ?></dd>
        <?php if ($r['email']): ?><dt>Email</dt><dd><?= e($r['email']) ?></dd><?php endif; ?>
        <dt>Account</dt><dd><?= $r['patient_user_id'] ? 'Registered patient' : 'No account (guest)' ?></dd>
      </dl>
    </section>
    <section class="panel" aria-labelledby="req-title">
      <div class="panel__head"><h2 id="req-title">Request</h2></div>
      <dl class="details">
        <dt>Reason</dt><dd><?= e(Labels::get(Labels::REASONS, $r['reason'])) ?></dd>
        <dt>Exposure</dt><dd><?= e(Labels::get(Labels::EXPOSURE, $r['exposure'])) ?></dd>
        <dt>Symptoms</dt><dd><?= $r['symptoms'] === [] ? 'None reported' : e(implode(', ', array_map(static fn ($x) => Labels::get(Labels::SYMPTOMS, $x), $r['symptoms']))) ?><?= $r['symptom_onset'] ? ' (since ' . e(fmt_date($r['symptom_onset'])) . ')' : '' ?></dd>
        <dt>Collection</dt><dd><?= e(Labels::get(Labels::COLLECTION_MODES, $r['collection_mode'])) ?></dd>
        <?php if ($r['collection_address']): ?><dt>Address</dt><dd><?= e($r['collection_address']) ?></dd><?php endif; ?>
        <dt>Location</dt><dd><?= e($r['city']) ?>, <?= e($r['state_name']) ?> <?= e($r['pincode']) ?></dd>
        <dt>Preferred</dt><dd><?= e(fmt_date($r['preferred_date'], 'D j M Y')) ?> · <?= e(Labels::get(Labels::SLOTS, $r['preferred_slot'])) ?></dd>
        <?php if ($r['notes']): ?><dt>Notes</dt><dd><?= e($r['notes']) ?></dd><?php endif; ?>
        <?php if ($r['assignment']): ?><dt>Phlebotomist</dt><dd><?= e($r['assignment']['phlebotomist_name']) ?> <span class="mono small">(<?= e($r['assignment']['employee_code']) ?>)</span></dd><?php endif; ?>
        <?php if ($r['sample']): ?><dt>Sample</dt><dd><?= e(Labels::get(Labels::SPECIMENS, $r['sample']['specimen'])) ?> · <span class="mono"><?= e($r['sample']['barcode']) ?></span><br><span class="small muted"><?= e(fmt_datetime($r['sample']['collected_at'])) ?> by <?= e($r['sample']['phlebotomist_name']) ?></span></dd><?php endif; ?>
      </dl>
    </section>

    <?php if (in_array($s, ['submitted', 'assigned', 'sample_collected'], true)): ?>
      <details class="confirm confirm--danger"<?= isset($errors['reason']) ? ' open' : '' ?>>
        <summary>Reject this request</summary>
        <div class="confirm__body">
          <form class="stack" method="post" action="<?= e(url('/admin/requests/' . (int) $r['id'] . '/reject')) ?>" data-once>
            <?= csrf_field() ?>
            <div class="field">
              <label for="f-reject-reason">Reason shown to the patient</label>
              <input class="input" id="f-reject-reason" name="reason" minlength="5" maxlength="300" required<?= aria_invalid($errors, 'reason') ?>>
              <?= field_error($errors, 'reason') ?>
            </div>
            <button class="btn btn--danger btn--small" type="submit">Reject request</button>
          </form>
        </div>
      </details>
    <?php endif; ?>
    <?php if (in_array($s, ['submitted', 'assigned'], true)): ?>
      <details class="confirm">
        <summary>Cancel on the patient’s behalf</summary>
        <div class="confirm__body">
          <form class="stack" method="post" action="<?= e(url('/admin/requests/' . (int) $r['id'] . '/cancel')) ?>" data-once>
            <?= csrf_field() ?>
            <div class="field">
              <label for="f-cancel-reason">Reason</label>
              <input class="input" id="f-cancel-reason" name="reason" maxlength="300">
            </div>
            <button class="btn btn--secondary btn--small" type="submit">Cancel request</button>
          </form>
        </div>
      </details>
    <?php endif; ?>
  </div>
</div>
