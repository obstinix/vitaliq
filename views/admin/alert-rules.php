<div class="app-head">
  <div>
    <h1>Alert rules</h1>
    <p><?= count(array_filter($rules, static fn ($r) => (int) $r['is_active'] === 1)) ?> active rules. When an event happens, matching rules notify a role or a person. Built-in alerts, such as critical results to the person who ordered the test, always go out as well.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="span-7 stack">
    <?php if ($rules === []): ?>
      <p class="empty">No rules yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Rule</th><th scope="col">Tells</th><th scope="col" class="num">Fired</th><th scope="col"><span class="visually-hidden">State</span></th></tr></thead>
          <tbody>
            <?php foreach ($rules as $r): ?>
              <tr>
                <td data-label="Rule"><?= e($r['name']) ?><span class="sub"><?= e($events[$r['event']][0] ?? $r['event']) ?><?= $r['match_value'] ? ' · when ' . e($r['match_value']) : '' ?></span></td>
                <td data-label="Tells"><?= e($r['recipient_name'] ?? ($roles[$r['recipient_role']] ?? '—')) ?></td>
                <td data-label="Fired" class="num"><?= (int) $r['hits'] ?><span class="sub"><?= $r['last_hit'] ? e(fmt_datetime($r['last_hit'])) : '—' ?></span></td>
                <td data-label="State"><form method="post" action="<?= e(url('/admin/alert-rules/' . (int) $r['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit"><?= (int) $r['is_active'] === 1 ? 'Pause' : 'Resume' ?></button></form></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <section class="panel" aria-labelledby="hits">
      <div class="panel__head"><h2 id="hits">Recently fired</h2></div>
      <?php if ($hits === []): ?><p class="empty">Nothing has fired yet.</p><?php else: ?>
        <ul class="rows"><?php foreach ($hits as $h): ?><li><span class="grow"><?= e($h['name']) ?><span class="sub"><?= e($h['summary']) ?></span></span><span class="end num"><?= e(fmt_datetime($h['created_at'])) ?><span class="sub"><?= (int) $h['recipients'] ?> told</span></span></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
  </section>
  <section class="panel span-5" aria-labelledby="new-rule">
    <div class="panel__head"><h2 id="new-rule">Add a rule</h2></div>
    <form method="post" action="<?= e(url('/admin/alert-rules')) ?>" novalidate><?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label for="f-name">Name</label><input class="input" id="f-name" name="name" maxlength="100" value="<?= e(old($old, 'name')) ?>"<?= aria_invalid($errors, 'name') ?>><?= field_error($errors, 'name') ?></div>
        <div class="field"><label for="f-event">When</label><select class="input" id="f-event" name="event"<?= aria_invalid($errors, 'event') ?>><?php foreach ($events as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'event'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'event') ?></div>
        <div class="field"><label for="f-match_value">Only when it is <span class="optional">optional</span></label><input class="input input--mono" id="f-match_value" name="match_value" maxlength="40" value="<?= e(old($old, 'match_value')) ?>"<?= aria_invalid($errors, 'match_value') ?>><?= field_error($errors, 'match_value') ?>
          <p class="hint"><?php foreach ($events as $k => [, $hint]): ?><span class="sub"><?= e($events[$k][0]) ?>: <?= e($hint) ?></span><?php endforeach; ?></p></div>
        <div class="field"><label for="f-recipient_role">Tell a role</label><select class="input" id="f-recipient_role" name="recipient_role"<?= aria_invalid($errors, 'recipient_role') ?>><option value="">—</option><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'recipient_role'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors, 'recipient_role') ?></div>
        <div class="field"><label for="f-recipient_user_id">Or one person</label><select class="input" id="f-recipient_user_id" name="recipient_user_id"<?= aria_invalid($errors, 'recipient_user_id') ?>><option value="0">—</option><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected(old($old, 'recipient_user_id'), (string) $s['id']) ?>><?= e($s['full_name']) ?></option><?php endforeach; ?></select><?= field_error($errors, 'recipient_user_id') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Add rule</button></div>
    </form>
  </section>
</div>
