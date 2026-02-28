<h3 class="mb-3"></h3>

<div class="row">
<div class="col-12">
<div class="card bg-info-subtle shadow-none position-relative overflow-hidden mb-4">
<div class="card-body px-4 py-3">
<div class="row align-items-center">
<div class="col-12">
<h4 class="fw-semibold mb-8">Módulos</h4>
<nav aria-label="breadcrumb">
<ol class="breadcrumb">
<li class="breadcrumb-item">
<a class="text-muted text-decoration-none" href="../main/index.html">Inicio</a>
</li>
<li class="breadcrumb-item" aria-current="page">Módulos</li>
</ol>
</nav>
</div>
</div>
</div>
</div>
</div>

<?php foreach ($modulos as $modulo): ?>

<div class="col-md-4 mb-4">
<a href="<?= $modulo->url ?>" class="card card-hover overflow-hidden text-decoration-none shadow-hover h-100">
<div class="d-flex flex-row align-items-center h-100">

<div class="p-4 text-bg-info d-flex align-items-center justify-content-center h-100">
<h3 class="text-white box mb-0">
<i class="ti ti-box"></i>
</h3>
</div>

<div class="p-3">
<h3 class="text-info mb-0 fs-6 text-break">
<?= htmlspecialchars($modulo->nombre_modulo) ?>
</h3>
<span class="text-muted small">Módulo</span>
</div>

</div>
</a>
</div>

<?php endforeach; ?>
</div>
