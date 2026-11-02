<?php
use Vitaliq\Services\AssistantService;
?>
<div class="app-head">
  <div>
    <h1><?= e(t('Ask the assistant')) ?></h1>
    <p><?= $providerName === null ? 'The assistant is not switched on for this service. Emergency guidance below still works.' : 'Answers come from ' . e($providerName) . '. It cannot see your records.' ?></p>
  </div>
  <?php if ($history !== []): ?>
    <form method="post" action="<?= e(url('/patient/assistant/clear')) ?>"><?= csrf_field() ?><button class="btn btn--ghost" type="submit">Delete conversation</button></form>
  <?php endif; ?>
</div>
<p class="disclaimer"><?= e(AssistantService::DISCLAIMER) ?></p>
<?= $view->partial('error-summary', ['errors' => $errors]) ?>
<section class="panel mt-4" aria-label="Conversation">
  <?php if ($history === []): ?>
    <p class="empty">Ask about tests, preparing for a sample, what a result usually means, or how to use Vitaliq. Do not include anyone's name or contact details.</p>
  <?php else: ?>
    <ol class="chat">
      <?php foreach ($history as $i => $m): ?>
        <li class="chat__msg chat__msg--<?= e($m['role']) ?><?= $m['source'] === 'safety' ? ' chat__msg--safety' : '' ?>"<?= $i === count($history) - 1 ? ' id="latest"' : '' ?>>
          <span class="visually-hidden"><?= $m['role'] === 'user' ? 'You said' : 'Assistant' ?>:</span>
          <?php foreach (preg_split('/\n{2,}/', (string) $m['content']) ?: [] as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach; ?>
          <time class="note-time" datetime="<?= e(iso_datetime($m['created_at'])) ?>"><?= e(fmt_datetime($m['created_at'])) ?></time>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/patient/assistant')) ?>" class="mt-4" novalidate>
    <?= csrf_field() ?>
    <div class="field"><label for="f-question">Your question</label><textarea class="input" id="f-question" name="question" rows="3" maxlength="<?= AssistantService::MAX_CHARS ?>"<?= aria_invalid($errors, 'question') ?>></textarea><?= field_error($errors, 'question') ?></div>
    <div class="form-actions"><button class="btn btn--primary" type="submit" data-busy-label="Asking…">Ask</button><a href="<?= e(url('/patient/triage')) ?>">Check symptoms instead</a></div>
  </form>
</section>
