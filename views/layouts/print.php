<!doctype html>
<html lang="en-IN">
<head>
<?= $view->partial('head', ['title' => $title ?? 'Report']) ?>
</head>
<body class="layout-print">
<main id="main">
  <?= $content ?>
</main>
<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
