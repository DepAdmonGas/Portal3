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

<button type="button"
class="btn btn-rounded btn-primary d-flex align-items-center"
data-bs-toggle="modal"
data-bs-target="#modalAgregarModulo">
<i class="ti ti-plus fs-4 me-2"></i>
Agregar
</button>
</div>

<!-- ✅ BREADCRUMB -->
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
<table id="table-catalogo" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>

<tr>
<th class="text-center" width="48px">#</th>
<th>Nombre del Módulo / Submodulo</th>
<th class="text-center">URL</th>
<th class="text-center" width="48px">
<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
</th>
</tr>

</thead>
<tbody></tbody>
</table>
</div>

</div>  
</div>


<!---------- MODAL AGREGAR MODULO ---------->
<div class="modal fade" id="modalAgregarModulo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" x-data="moduloPuestoForm()">
<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">

<div class="modal-header bg-primary">
<h4 class="modal-title text-white">Agregar módulo / submodulo</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">
<div class="row">

<div class="col-12 mb-3">
<label class="form-label fw-semibold">Nombre del módulo:</label>
<textarea class="form-control" rows="3" placeholder="Nombre del módulo..." x-model="nombreModulo" @input="error = false":class="error ? 'border border-danger' : ''"></textarea>
<small class="text-danger" x-show="error">Debes ingresar el nombre de módulo</small>
</div>

<div class="col-12">
<label class="form-label fw-semibold">URL:</label>
<input type="text" class="form-control" placeholder="Escribe la URL o nombre del módulo..." x-model="nombreURL" @input="error = false":class="error ? 'border border-danger' : ''">
<small class="text-danger" x-show="error">Debes ingresar la URL del modulo</small>
</div>

</div>
</div>

<div class="modal-footer">
<button class="btn bg-danger-subtle text-danger"data-bs-dismiss="modal" :disabled="enviando" @click="resetForm()"> Cancelar </button>
<button class="btn btn-success" @click="guardar()" :disabled="enviando">
<span x-show="!enviando">Guardar</span>
<span x-show="enviando">Guardando...</span>
</button>
</div>

</div>
</div>
</div>




