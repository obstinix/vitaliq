<div class="app-head">
  <div>
    <p class="eyebrow">Community</p>
    <h1>Announcements</h1>
    <p>Public-health updates must cite a source. Service notices describe this deployment.</p>
  </div>
</div>
<div class="grid-2">
  <section aria-labelledby="list-title">
    <h2 id="list-title" class="visually-hidden">All announcements</h2>
    <?php if ($list === []): ?>
      <div class="empty"><h3>No announcements</h3><p>Publish the first one using the form.</p></div>
    <?php else: ?>
      <ul class="notice-list">
        <?php foreach ($list as $a): ?>
          <li>
            <time class="notice-date" datetime="<?= e($a['published_on']) ?>"><?= e(fmt_date($a['published_on'])) ?></time>
            <div>
              <h3><?= e($a['title']) ?></h3>
              <p><?= e($a['summary']) ?></p>
              <div class="cluster">
                <span class="tag"><?= e($categories[$a['category']] ?? $a['category']) ?></span>
                <?php if ((int) $a['is_demo'] === 1): ?><span class="tag tag--demo">Demo</span><?php endif; ?>
                <span class="status status--<?= (int) $a['is_published'] === 1 ? 'pass' : 'blocked' ?>"><?= (int) $a['is_published'] === 1 ? 'Published' : 'Hidden' ?></span>
                <form class="inline-form" method="post" action="<?= e(url('/admin/announcements/' . (int) $a['id'] . '/toggle')) ?>">
                  <?= csrf_field() ?>
                  <button class="btn btn--ghost btn--small" type="submit"><?= (int) $a['is_published'] === 1 ? 'Hide' : 'Publish' ?></button>
                </form>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <section class="panel" aria-labelledby="new-title">
    <div class="panel__head"><h2 id="new-title">New announcement</h2></div>
    <?= $view->partial('error-summary', ['errors' => $errors]) ?>
    <form class="stack" method="post" action="<?= e(url('/admin/announcements')) ?>" novalidate data-once>
      <?= csrf_field() ?>
      <div class="field">
        <label for="f-category">Category</label>
        <select class="input" id="f-category" name="category" required<?= aria_invalid($errors, 'category') ?>>
          <?php foreach ($categories as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($old, 'category'), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
        <?= field_error($errors, 'category') ?>
      </div>
      <div class="field">
        <label for="f-title">Title</label>
        <input class="input" id="f-title" name="title" maxlength="160" required value="<?= e(old($old, 'title')) ?>"<?= aria_invalid($errors, 'title') ?>>
        <?= field_error($errors, 'title') ?>
      </div>
      <div class="field">
        <label for="f-summary">Summary</label>
        <textarea class="input" id="f-summary" name="summary" maxlength="400" required<?= aria_invalid($errors, 'summary') ?>><?= e(old($old, 'summary')) ?></textarea>
        <?= field_error($errors, 'summary') ?>
      </div>
      <div class="field">
        <label for="f-source_name">Source name</label>
        <input class="input" id="f-source_name" name="source_name" maxlength="120" value="<?= e(old($old, 'source_name')) ?>"<?= aria_invalid($errors, 'source_name') ?>>
        <?= field_error($errors, 'source_name') ?>
      </div>
      <div class="field">
        <label for="f-source_url">Source link (https)</label>
        <input class="input" id="f-source_url" name="source_url" type="url" maxlength="300" value="<?= e(old($old, 'source_url')) ?>"<?= aria_invalid($errors, 'source_url') ?>>
        <?= field_error($errors, 'source_url') ?>
      </div>
      <div class="field">
        <label for="f-published_on">Publication date</label>
        <input class="input" id="f-published_on" name="published_on" type="date" required value="<?= e(old($old, 'published_on')) ?>"<?= aria_invalid($errors, 'published_on') ?>>
        <?= field_error($errors, 'published_on') ?>
      </div>
      <button class="btn btn--primary" type="submit">Publish</button>
    </form>
  </section>
</div>
