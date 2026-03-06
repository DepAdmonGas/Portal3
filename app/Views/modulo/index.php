<div class="row">
<div class="col-12">
<div class="card bg-info-subtle position-relative overflow-hidden mb-3">
<div class="card-body px-4 py-3">
<div class="row align-items-center">
<div class="col-12">
<div class="d-flex justify-content-between align-items-center mb-2">
<h4 class="fw-semibold mb-0 d-flex align-items-center gap-2">
<i class="ti ti-sitemap fs-4"></i>
<?= htmlspecialchars($title) ?>
</h4>
</div>

<nav aria-label="breadcrumb">
<ol class="breadcrumb mb-0">
<li class="breadcrumb-item">
<a href="/main" class="text-muted text-decoration-none">
<i class="ti ti-home-2 me-1"></i> Inicio
</a>
</li>

<?php foreach ($breadcrumb as $index => $item): ?>
<?php if ($index === count($breadcrumb) - 1): ?>
<li class="breadcrumb-item active fw-semibold">
<?= htmlspecialchars($item->nombre_modulo) ?>
</li>
<?php else: ?>
<li class="breadcrumb-item">
<a href="/<?= $item->url ?>" class="text-muted text-decoration-none">
<?= htmlspecialchars($item->nombre_modulo) ?>
</a>
</li>
<?php endif; ?>
<?php endforeach; ?>
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
<i class="ti ti-folder"></i>
</h3>
</div>

<div class="p-3">
<h3 class="text-info mb-0 fs-6 text-break">
<?= htmlspecialchars($modulo->nombre_modulo) ?>
</h3>
<span class="text-muted small">Submódulo</span>
</div>

</div>
</a>
</div>

<?php endforeach; ?>
</div>