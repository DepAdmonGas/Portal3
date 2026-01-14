<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="light" data-color-theme="Blue_Theme" data-layout="vertical">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $title ?? 'Portal3' ?></title>

     <!-- Favicon icon-->
    <link rel="shortcut icon" type="image/png" href="/assets/images/logos/favicon.png" />
    <!-- Core Css -->
    <link rel="stylesheet" href="/assets/css/styles.css" />
    <!-- Alpine + Axios -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

</head>
<body class="link-sidebar">

    <?= $content ?>

     <div class="dark-transparent sidebartoggler"></div>
  <!-- Import Js Files -->
  <script src="/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/libs/simplebar/dist/simplebar.min.js"></script>
  <script src="/assets/js/theme/app.init.js"></script>
  <script src="/assets/js/theme/theme.js"></script>
  <script src="/assets/js/theme/app.min.js"></script>
  <script src="/assets/js/theme/sidebarmenu.js"></script>

  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
  <!-- highlight.js (code view) -->
  <script src="/assets/js/highlights/highlight.min.js"></script>

    <!-- Scripts por vista -->
    <?php if (!empty($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script src="<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

  <script>
  hljs.initHighlightingOnLoad();
  document.querySelectorAll("pre.code-view > code").forEach((codeBlock) => {
    codeBlock.textContent = codeBlock.innerHTML;
  });
 </script>

</body>
</html>
