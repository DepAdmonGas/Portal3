<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="light" data-color-theme="Blue_Theme" data-layout="vertical">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= $title ?? 'Portal3' ?></title>

<!-- Favicon icon-->
<link rel="shortcut icon" type="image/png" href="<?=asset('images/logos/icono-web.png')?>" />
<!-- Core Css -->
<link rel="stylesheet" href="<?= asset('css/styles.css') ?>" />
<link rel="stylesheet" href="<?= asset('libs/sweetalert2/dist/sweetalert2.min.css') ?>">


<!-- Scripts por vista -->
<?php if (!empty($links)): ?>
<?php foreach ($links as $link): ?>
<link rel="stylesheet" href="<?= $link ?>" />
<?php endforeach; ?>
<?php endif; ?>

<!-- Alpine + Axios -->
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>

<body class="link-sidebar">

<!-- Pantalla de carga (Loader) -->
<div class="loader-admongas">
    <img src="<?=asset('images/logos/logo-empresaMov.gif')?>" alt="Cargando..." class="logo-loader-admongas" />
</div>


<div id="main-wrapper">
<!-- Sidebar Start -->
<aside class="left-sidebar with-vertical">

<!-- Logo Sidebar -->
<div class="brand-logo d-flex align-items-center justify-content-between">
<a href="/home" class="text-nowrap logo-img mt-3 mb-3">
<img src="<?=asset('images/logos/Logo.png')?>" class="dark-logo w-100" alt="Logo Admongas" />
<img src="<?=asset('images/logos/Logo-dark.png')?>" class="light-logo w-100" alt="Logo Admongas Dark" />
</a>
<a href="javascript:void(0)" class="sidebartoggler ms-auto text-decoration-none fs-5 d-block d-xl-none"><i class="ti ti-x"></i></a>
</div>

<nav class="sidebar-nav scroll-sidebar" data-simplebar>
<ul id="sidebarnav" class="mt-3" >

<li class="sidebar-item">
<a class="sidebar-link" href="/home" aria-expanded="false">
<span>
<i class="ti ti-home"></i>
</span>
<span class="hide-menu">Home</span>
</a>
</li>

<!---------- DIRECCION DE OPERACIONES ---------->
<li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">DIRECCIÓN DE OPERACIONES</span></li>

<li class="sidebar-item">
<a class="sidebar-link" href="/departamento-operativo/corte-diario" aria-expanded="false">
<span><i class="ti ti-report-money"></i></span>
<span class="hide-menu">Corte Diario</span>
</a>
</li>

<li class="sidebar-item">
<a class="sidebar-link" href="/departamento-operativo/solicitud-cheques" aria-expanded="false">
<span><i class="ti ti-file-check"></i></span>
<span class="hide-menu">Solicitud de Cheques</span>
</a>
</li>

<li class="sidebar-item">
<a class="sidebar-link" href="/departamento-operativo/recursos-humanos/recibos-nomina" aria-expanded="false">
<span><i class="ti ti-receipt"></i></span>
<span class="hide-menu">Recibos de Nomina</span>
</a>
</li>

<li class="sidebar-item">
<a class="sidebar-link" href="/departamento-operativo/importacion/descarga-merma" aria-expanded="false">
<span><i class="ti ti-trash"></i></span>
<span class="hide-menu">Descarga de merma</span>
</a>
</li>

<li class="sidebar-item">
<a class="sidebar-link" href="/departamento-operativo/importacion/precios-combustible" aria-expanded="false">
<span><i class="ti ti-gas-station"></i></span>
<span class="hide-menu">Precios diarios combustible</span>
</a>
</li>

<li class="sidebar-item">
<a class="sidebar-link" href="/departamento-operativo/importacion/cuenta-litros" aria-expanded="false">
<span><i class="ti ti-droplet"></i></span>
<span class="hide-menu">Cuenta Litros</span>
</a>
</li>

<li class="sidebar-item">
<a class="sidebar-link" href="/departamento-operativo/almacen/orden-mantenimiento" aria-expanded="false">
<span><i class="ti ti-tool"></i></span>
<span class="hide-menu">Orden de Mantenimiento</span>
</a>
</li>

</ul>
</nav>


<div class="bg-footer-do">
<div class="fixed-profile p-2 mx-2 mb-5 bg-secondary-subtle-do rounded mt-0">
<div class="hstack gap-2">
<div class="john-img">
<img src="<?= asset('images/profile/user-1.jpg') ?>" class="rounded-circle" width="40" height="40" alt="modernize-img" />
</div>
<div class="john-title">
<h6 class="mb-0 fs-5 fw-normal text-white"><?=implode(' ', array_slice(explode(' ', trim($user->nombre)), 0, 2));?></h6>
<span class="fs-2"><?=$user->puesto->tipo_puesto?></span>
</div>
<a href="/logout" class="border-0 bg-transparent text-primary ms-auto" tabindex="0" type="button" aria-label="logout" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Salir">
<i class="ti ti-power text-danger fs-6"></i>
</a>
</div>
</div>
</div>

</aside>

<!--  Sidebar End -->
<div class="page-wrapper">
<!--  Header Start -->
<header class="topbar">
<div class="with-vertical"><!-- ---------------------------------- -->
<!-- Start Vertical Layout Header -->
<!-- ---------------------------------- -->
<nav class="navbar navbar-expand-lg p-0">
<ul class="navbar-nav">
<li class="nav-item nav-icon-hover-bg rounded-circle ms-n2">
<a class="nav-link sidebartoggler" id="headerCollapse" href="javascript:void(0)">
<i class="ti ti-menu-2"></i>
</a>
</li>
</ul>

<div class="d-block d-lg-none py-2 text-center">
<a href="/home" class="d-flex justify-content-center align-items-center logo-navbar">
<img src="<?=asset('images/logos/Logo.png')?>" class="dark-logo img-fluid" alt="Logo Admongas" />
<img src="<?=asset('images/logos/Logo-dark.png')?>" class="light-logo img-fluid" alt="Logo Admongas Dark" />
</a>
</div>

<a class="navbar-toggler nav-icon-hover-bg rounded-circle p-0 mx-0 border-0" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
<i class="ti ti-dots fs-7"></i>
</a>
<div class="collapse navbar-collapse justify-content-end" id="navbarNav">
<div class="d-flex align-items-center justify-content-between">

<ul class="navbar-nav flex-row ms-auto align-items-center justify-content-center">

<li class="nav-item nav-icon-hover-bg rounded-circle">
<a class="nav-link moon dark-layout" href="javascript:void(0)" style="display: flex;">
<i class="ti ti-moon moon" style="display: flex;"></i>
</a>
<a class="nav-link sun light-layout" href="javascript:void(0)" style="display: none;">
<i class="ti ti-sun sun" style="display: none;"></i>
</a>
</li>

<!-- ------------------------------- -->
<!-- start profile Dropdown -->
<!-- ------------------------------- -->
<li class="nav-item dropdown">

<a class="nav-link pe-0" href="javascript:void(0)" id="drop1" aria-expanded="false">
<div class="d-flex align-items-center">
<div class="user-profile-img">
<img src="<?= asset('images/profile/user-1.jpg') ?>" class="rounded-circle" width="35" height="35" alt="modernize-img" />
</div>
</div>
</a>

<div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop1">
<div class="profile-dropdown position-relative" data-simplebar>
<div class="py-3 px-7 pb-0"><h5 class="mb-0 fs-5 fw-semibold">Perfil de Usuario</h5></div>

<div class="d-flex align-items-center py-9 mx-7 border-bottom">
<img src="<?= asset('images/profile/user-1.jpg') ?>" class="rounded-circle" width="80" height="80" alt="modernize-img" />

<div class="ms-3 user-info">
<h5 class="mb-1 fs-3"><?= $user->nombre ?></h5>
<span class="mb-1 d-block"><?= $user->puesto->tipo_puesto ?></span>
<span class="mb-1 d-block mt-3"><i class="ti ti-mail fs-4"></i> Correo electronico:</span>
<p class="mb-0 d-flex align-items-center gap-2"><span><?= $user->email ?></span></p>
</div>
</div>

<div class="message-body">
<a href="" class="py-8 px-7 mt-8 d-flex align-items-center">
<span class="d-flex align-items-center justify-content-center text-bg-light rounded-1 p-6">
<img src="<?= asset('images/svgs/icon-account.svg') ?>" alt="modernize-img" width="24" height="24" />
</span>

<div class="w-100 ps-3">
<h6 class="mb-1 fs-3 fw-semibold lh-base">Mi Perfil</h6>
<span class="fs-2 d-block text-body-secondary">Configuración</span>
</div>
</a>
</div>

<div class="d-grid py-4 px-7 pt-8">
<a href="/logout" class="btn btn-outline-primary">Salir</a>
</div>
</div>
</div>
</li>
<!-- ------------------------------- -->
<!-- end profile Dropdown -->
<!-- ------------------------------- -->
</ul>
</div>
</div>
</nav>
<!-- ---------------------------------- -->
<!-- End Vertical Layout Header -->
<!-- ---------------------------------- -->

</div>      
</header>
<!--  Header End -->
<div class="body-wrapper">
<div class="container-fluid">

<div class="d-flex align-items-center">
<!-- IZQUIERDA -->
<?php
$idEstacion    = $filtro_usuario['id_estacion'] ?? null;
$multiestacion = !empty($filtro_usuario['multiestacion']);
$esTodas       = (int)$idEstacion === 8;

// Obtener razón social según tipo
$razonsocial = $multiestacion
? ($filtro_usuario['razonsocial'] ?? '')
: ($user->estacion->razonsocial ?? '');

$razonsocial = trim($razonsocial);
?>

<?php if (!$esTodas && $razonsocial !== ''): ?>
<span class="mb-1 badge rounded-pill text-bg-info">
<?= $razonsocial ?>
</span>
<?php endif; ?>

<!-- DERECHA -->
<?php if ($multiestacion) : ?>
<select id="selectEstacion" class="form-select form-select-sm w-auto ms-auto">
<option value="8" <?= $esTodas ? 'selected' : '' ?>>Todas las estaciones</option>

<?php if (!empty($estaciones)) : ?>
<?php foreach ($estaciones as $estacion) : ?>
<option value="<?= $estacion->id ?>"<?= $idEstacion == $estacion->id ? 'selected' : '' ?>><?= $estacion->nombre ?></option>
<?php endforeach; ?>
<?php endif; ?>

</select>
<?php endif; ?>
</div>

<h4 class="fw-semibold mt-3"><?=$title;?></h4>
<?php \App\Core\Breadcrumb::render(); ?>

<?= $content ?>

</div>
</div>

</div>

</div>

<div class="dark-transparent sidebartoggler"></div>
<!-- Import Js Files -->
<script src="<?=asset('js/home/actions-home.init.js?v=1.1')?>"></script>
<script src="<?=asset('js/switch.estacion.min.js')?>"></script>
<script src="<?=asset('js/loader.min.js')?>"></script>
<script src="<?=asset('libs/bootstrap/dist/js/bootstrap.bundle.min.js')?>"></script>
<script src="<?=asset('libs/simplebar/dist/simplebar.min.js')?>"></script>
<script src="<?=asset('js/theme/app.init.js')?>"></script>
<script src="<?=asset('js/theme/theme.js')?>"></script>
<script src="<?=asset('js/theme/app.min.js')?>"></script>
<script src="<?=asset('js/theme/sidebarmenu.js')?>"></script>

<!-- solar icons -->
<script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
<!-- highlight.js (code view) -->
<script src="<?= asset('js/highlights/highlight.min.js') ?>"></script>
<script src="<?= asset('libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>
<script src="<?= asset('js/core/notify.js?v=1.1') ?>"></script> 
<script src="<?= asset('js/core/actions.alpine.js?v=1.1') ?>"></script>

<!-- Scripts por vista -->
<?php if (!empty($scripts)): ?>
<?php foreach ($scripts as $script): ?>
<script src="<?= $script ?>"></script>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
