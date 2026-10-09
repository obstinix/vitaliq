<!doctype html>
<html lang="en-IN">
<head>
<?= $view->partial('head', ['title' => $title ?? 'Vitaliq']) ?>
<script src="<?= e(asset('js/motion-gate.js')) ?>"></script>
</head>
<body class="layout-public <?= e($pageClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?= $view->partial('masthead', ['currentUser' => $currentUser ?? null, 'currentPath' => $currentPath ?? '/']) ?>
<main id="main" tabindex="-1">
  <?php if (!empty($flash) && array_filter($flash)): ?>
    <div class="wrap"><?= $view->partial('flash', ['flash' => $flash]) ?></div>
  <?php endif; ?>
  <?= $content ?>
</main>
<?= $view->partial('footer', []) ?>
<?= $view->partial('scripts', ['scripts' => $scripts ?? [], 'motion' => true, 'clientRules' => $clientRules ?? []]) ?>
</body>
</html>
