<!DOCTYPE html>
<html
    lang="es"
    dir="ltr"
    data-bs-theme="light"
    data-color-theme="Blue_Theme"
    data-layout="vertical">

<head>
    <meta charset="UTF-8">
    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0" />
    <title><?= $title ?? 'Portal3' ?></title>
    <link
        rel="shortcut icon"
        type="image/png"
        href="<?= asset('images/logos/icono-web.png') ?>" />

    <!-- Core Css -->
    <link
        rel="stylesheet"
        href="<?= asset('css/styles.css') ?>" />

    <!-- DOMPurify -->
    <script
        src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>

    <!-- Alpine + Axios -->
    <script
        defer
        src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body>
    <?= $content ?>

    <!-- Import Js Files -->

    <script
        src="<?= asset('js/loader.min.js') ?>"></script>
    <script
        src="<?= asset('libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
    <script
        src="<?= asset('libs/simplebar/dist/simplebar.min.js') ?>"></script>
    <script
        src="<?= asset('js/theme/app.init.js') ?>"></script>
    <script
        src="<?= asset('js/theme/theme.js') ?>"></script>
    <script
        src="<?= asset('js/theme/app.min.js') ?>"></script>

    <!-- Solar Icons -->

    <script
        src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

    <!-- Scripts por vista -->

    <?php if (!empty($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script
                src="<?= asset($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>

</html>