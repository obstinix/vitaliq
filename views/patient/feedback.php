<div class="app-head">
  <div>
    <h1>Feedback</h1>
    <p>Tell us how booking, collection and results worked for you. The operations team reads every message.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="panel span-7" aria-labelledby="fb-new">
    <div class="panel__head"><h2 id="fb-new">Send feedback</h2></div>
    <form method="post" action="<?= e(url('/patient/feedback')) ?>" novalidate>
      <?= csrf_field() ?>
      <fieldset class="mb-0" id="f-rating">
        <legend>How was it overall?</legend>
        <div class="choice-grid">
          <?php foreach ([5 => 'Very good', 4 => 'Good', 3 => 'Fair', 2 => 'Poor', 1 => 'Very poor'] as $n => $l): ?>
            <label class="choice"><input type="radio" name="rating" value="<?= $n ?>"<?= checked(old($old, 'rating') === (string) $n) ?>><span class="choice__text"><?= $n ?> · <?= e($l) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'rating') ?>
      </fieldset>
      <div class="form-grid form-grid--2 mt-4">
        <div class="field"><label for="f-category">Topic</label>
          <select class="input" id="f-category" name="category"<?= aria_invalid($errors, 'category') ?>><option value="">Choose</option>
            <?php foreach ($categories as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'category'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
          </select><?= field_error($errors, 'category') ?></div>
        <div class="field"><label for="f-request_id">About a request <span class="optional">optional</span></label>
          <select class="input" id="f-request_id" name="request_id"<?= aria_invalid($errors, 'request_id') ?>><option value="">None</option>
            <?php foreach ($requests as $r): ?><option value="<?= (int) $r['id'] ?>"<?= selected(old($old, 'request_id'), (string) $r['id']) ?>><?= e($r['reference']) ?></option><?php endforeach; ?>
          </select><?= field_error($errors, 'request_id') ?></div>
        <div class="field span-all"><label for="f-comments">Comments <span class="optional">optional</span></label><textarea class="input" id="f-comments" name="comments" maxlength="1000" rows="4"><?= e(old($old, 'comments')) ?></textarea><?= field_error($errors, 'comments') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Send feedback</button></div>
    </form>
  </section>
  <section class="panel span-5" aria-labelledby="fb-mine">
    <div class="panel__head"><h2 id="fb-mine">What you have sent</h2></div>
    <?php if ($mine === []): ?>
      <p class="empty">You have not sent feedback yet.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($mine as $f): ?>
          <li><span class="grow"><?= e($categories[$f['category']] ?? $f['category']) ?><?= $f['reference'] ? ' · <span class="ref">' . e($f['reference']) . '</span>' : '' ?>
            <span class="sub"><?= e(fmt_date($f['created_at'])) ?><?= $f['admin_note'] ? ' · Reply: ' . e($f['admin_note']) : '' ?></span></span>
            <span class="end num"><?= (int) $f['rating'] ?>/5<span class="sub"><?= e(ucfirst((string) $f['status'])) ?></span></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
