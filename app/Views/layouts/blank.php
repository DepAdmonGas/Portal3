<!DOCTYPE html>
<html lang="es" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $title ?? 'Portal3' ?></title>
    <link rel="stylesheet" href="<?= asset('libs/bootstrap/dist/css/bootstrap.min.css') ?>" />
    <link rel="stylesheet" href="<?= asset('fonts/tabler-icons/tabler-icons.css') ?>" />
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>

<body class="bg-white">
    <?= $content ?>
</body>

</html>
