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

<!-- ✅ BREADCRUMB -->
<nav aria-label="breadcrumb">
<ol class="breadcrumb mb-0">
<li class="breadcrumb-item">
<a href="/home" class="text-muted text-decoration-none">
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
<!-- ✅ FIN BREADCRUMB -->

</div>
</div>
</div>
</div>

</div>
</div>
   
<div class="col-12">
<div class="datatables">

<div class="table-responsive">
<table id="table-puestos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>

<tr>
<th>#</th>
<th>Nombre del Puesto</th>
<th>Estatus</th>
<th class="text-center">
<a class="text-muted"><i class="ti ti-eye fs-6"></i></a>
</th>
</tr>

</thead>
<tbody></tbody>
</table>
</div>

</div>  
</div>





