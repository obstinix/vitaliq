<div class="app-head">
  <div>
    <h1>Facilities</h1>
    <p><?= count(array_filter($rows, static fn ($r) => (int) $r['is_active'] === 1)) ?> open of <?= count($rows) ?>. Facilities appear in booking, lab orders, schedules and the facility comparison.</p>
  </div>
</div>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<div class="vq-grid">
  <section class="span-8">
    <?php if ($rows === []): ?>
      <p class="empty">No facilities yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th scope="col">Facility</th><th scope="col">Type</th><th scope="col" class="num">Staff</th><th scope="col" class="num">Appointments</th><th scope="col" class="num">Lab orders</th><th scope="col">State</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $f): ?>
              <tr>
                <td data-label="Facility"><a href="<?= e(url('/admin/facilities/' . (int) $f['id'])) ?>"><?= e($f['name']) ?></a><span class="sub"><span class="ref"><?= e($f['code']) ?></span> · <?= e($f['city']) ?>, <?= e($f['state_name']) ?></span></td>
                <td data-label="Type"><?= e($kinds[$f['kind']]) ?></td>
                <td data-label="Staff" class="num"><?= (int) $f['staff'] ?></td>
                <td data-label="Appointments" class="num"><?= fmt_number($f['appointments']) ?></td>
                <td data-label="Lab orders" class="num"><?= fmt_number($f['orders']) ?></td>
                <td data-label="State"><span class="status status--<?= (int) $f['is_active'] === 1 ? 'active' : 'cancelled' ?>"><?= (int) $f['is_active'] === 1 ? 'Open' : 'Closed' ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <section class="panel span-4" aria-labelledby="new-f">
    <div class="panel__head"><h2 id="new-f">Add a facility</h2></div>
    <?= $view->partial('facility-fields', ['action' => url('/admin/facilities'), 'f' => $old, 'kinds' => $kinds, 'states' => $states, 'errors' => $errors, 'button' => 'Add facility']) ?>
  </section>
</div>
