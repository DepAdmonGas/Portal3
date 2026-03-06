<div class="row">

<div class="col-12">
<div class="card bg-info-subtle position-relative overflow-hidden mb-3">
<div class="card-body px-4 py-3">
<div class="row align-items-center">
<div class="col-12">

<div class="d-flex justify-content-between align-items-center mb-2">
<h4 class="fw-semibold mb-0 d-flex align-items-center gap-2">
<i class="ti ti-sitemap fs-4"></i>
Configuracion de Modulos (Usuario) - <?=$usuario->nombre?>
</h4>

<button type="button"
class="btn btn-rounded btn-primary d-flex align-items-center"
data-bs-toggle="modal"
data-bs-target="#modalAgregarModulo">
<i class="ti ti-plus fs-4 me-2"></i>
Agregar módulo
</button>
</div>

<nav aria-label="breadcrumb">
<ol class="breadcrumb mb-0">
<li class="breadcrumb-item text-muted text-decoration-none" onclick="history.go(-1)">
Regresar
</li>
<li class="breadcrumb-item">Configuracion de Modulos (Usuario) - <?=$usuario->nombre?></li>
</ol>
</nav>

</div>
</div>
</div>
</div>
</div>

<div class="col-12" id="estructuraContainer">
<div class="row">

<?php
$tree = [];

foreach ($modulos as $modulo) {
$parent = $modulo->id_modulo_principal ?: 0;
$tree[$parent][] = $modulo;
}


/* ============================================================
FUNCIÓN PARA SUB-SUB-MÓDULOS (RECURSIVA)
============================================================ */

function renderTreeLines($tree, $parentId, $idPuesto)
{
if (!isset($tree[$parentId])) return;

echo '<ul class="tree-lines list-unstyled mt-3">';

foreach ($tree[$parentId] as $node) {

echo '<li class="mb-3">';

echo '
<div class="d-flex justify-content-between align-items-center w-100">

<div class="d-flex align-items-center gap-2">
<span class="badge text-bg-primary p-2 d-inline-flex align-items-center gap-2">
<i class="ti ti-folder fs-6"></i>
'.$node->nombre_modulo.'
</span>
</div>

<div class="d-flex gap-1">

<button class="btn btn-sm btn-primary btnAbrirAsignarSubmodulo"
data-bs-toggle="modal"
data-bs-target="#modalAgregarSubmodulo"
data-id-estructura="'.$node->id_estructura.'"
data-nombre="'.htmlspecialchars($node->nombre_modulo).'">
<i class="ti ti-plus"></i>
</button>

<button class="btn btn-sm btn-warning btnEditarPermisos"
data-bs-toggle="modal"
data-bs-target="#modalEditarPermisos"
data-id-estructura="'.$node->id_estructura.'"
data-id-usuario="'.$idPuesto.'"
data-nombre="'.htmlspecialchars($node->nombre_modulo).'">
<i class="ti ti-key text-dark"></i>
</button>

<button class="btn btn-sm btn-danger btn-delete"
data-id-estructura="'.$node->id_estructura.'"
data-id-usuario="'.$idPuesto.'"
data-id-modulo="'.$node->id_modulo.'"
data-id-modulo-principal="'.($node->id_modulo_principal ?: 0).'">
<i class="ti ti-trash"></i>
</button>

</div>
</div>
';

renderTreeLines($tree, $node->id_estructura, $idPuesto);

echo '</li>';
}

echo '</ul>';
}


/* ============================================================
FUNCIÓN PARA SUBMÓDULOS (CARDS)
============================================================ */

function renderSubModulosCards($tree, $parentId, $idPuesto)
{
if (!isset($tree[$parentId]) || empty($tree[$parentId])) {

echo '
<div class="alert alert-info text-center mb-3">
<i class="ti ti-info-circle fs-4"></i>
Este apartado no cuenta con submódulos
</div>';

return;
}

foreach ($tree[$parentId] as $sub) {

echo '<div class="row">';
echo '<div class="col-12">';

echo '
<div class="card submodule-card">
<div class="card-body p-3">

<div class="d-flex justify-content-between align-items-center">

<div class="d-flex align-items-start gap-3">
<div class="bg-primary-subtle text-primary icon-box">
<i class="ti ti-sitemap fs-5"></i>
</div>

<div>
<h5 class="mb-1">'.$sub->nombre_modulo.'</h5>
<h6 class="text-muted">Submódulo</h6>
</div>
</div>

<div class="d-flex gap-1">

<button class="btn btn-sm btn-primary btnAbrirAsignarSubmodulo"
data-bs-toggle="modal"
data-bs-target="#modalAgregarSubmodulo"
data-id-estructura="'.$sub->id_estructura.'"
data-nombre="'.htmlspecialchars($sub->nombre_modulo).'">
<i class="ti ti-plus"></i>
</button>

<button class="btn btn-sm btn-warning btnEditarPermisos"
data-bs-toggle="modal"
data-bs-target="#modalEditarPermisos"
data-id-estructura="'.$sub->id_estructura.'"
data-id-usuario="'.$idPuesto.'"
data-nombre="'.htmlspecialchars($sub->nombre_modulo).'">
<i class="ti ti-key text-dark"></i>
</button>

<button class="btn btn-sm btn-danger btn-delete"
data-id-estructura="'.$sub->id_estructura.'"
data-id-usuario="'.$idPuesto.'"
data-id-modulo="'.$sub->id_modulo.'"
data-id-modulo-principal="'.($sub->id_modulo_principal ?: 0).'">
<i class="ti ti-trash"></i>
</button>

</div>

</div>';

renderTreeLines($tree, $sub->id_estructura, $idPuesto);

echo '
</div>
</div>';

echo '</div>';
echo '</div>';
}
}


/* ============================================================
RENDER PRINCIPAL (PADRES)
============================================================ */

if (isset($tree[0])) {

foreach ($tree[0] as $moduloPadre) {
?>

<div class="col-xl-4 col-lg-6 col-md-12 col-sm-12">
<div class="card">

<div class="card-header text-bg-info d-flex justify-content-between align-items-center">

<h4 class="mb-0 text-white card-title d-flex align-items-center gap-3">
<i class="ti ti-box fs-4"></i>
<?= $moduloPadre->nombre_modulo ?>
</h4>

<div class="d-flex gap-1">

<button class="btn btn-sm btn-light btnAbrirAsignarSubmodulo"
data-bs-toggle="modal"
data-bs-target="#modalAgregarSubmodulo"
data-id-estructura="<?= $moduloPadre->id_estructura ?>"
data-nombre="<?= htmlspecialchars($moduloPadre->nombre_modulo) ?>">
<i class="ti ti-plus"></i>
</button>

<button class="btn btn-sm btn-warning btnEditarPermisos"
data-bs-toggle="modal"
data-bs-target="#modalEditarPermisos"
data-id-estructura="<?= $moduloPadre->id_estructura ?>"
data-id-usuario="<?= $idPuesto ?? $usuario->id ?>"
data-nombre="<?= htmlspecialchars($moduloPadre->nombre_modulo) ?>">
<i class="ti ti-key text-dark"></i>
</button>

<button class="btn btn-sm btn-danger btn-delete"
data-id-estructura="<?= $moduloPadre->id_estructura ?>"
data-id-usuario="<?= $usuario->id ?>"
data-id-modulo="<?= $moduloPadre->id_modulo ?>"
data-id-modulo-principal="0">
<i class="ti ti-trash"></i>
</button>

</div>

</div>

<div class="card-body pb-0">
<?php renderSubModulosCards($tree, $moduloPadre->id_estructura, $usuario->id); ?>
</div>

</div>
</div>

<?php
}

} else {
?>

<div class="col-12">
<section class="mb-5">
<div class="bg-warning-subtle rounded-3 position-relative overflow-hidden">
<div class="row">
<div class="col-lg-12">
<div class="py-5 text-center">
<h2 class="fw-bolder">
Este puesto no cuenta con módulos configurados
</h2>
<p class="mb-0">
<span class="fw-bolder">Debes agregar un módulo</span> para poder ver la información.
</p>
</div>
</div>
</div>
</div>
</section>
</div>

<?php } ?>

</div>
</div>
</div>

<!---------- MODAL AGREGAR MODULO ---------->
<div class="modal fade" id="modalAgregarModulo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" 
data-id-usuario="<?=$usuario->id ?>" x-data="moduloUsuarioForm()">
<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">

<div class="modal-header bg-primary">
<h4 class="modal-title text-white">Agregar módulo (<?=$usuario->nombre?>)</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">
<label class="form-label fw-semibold">Nombre del módulo:</label>
<select class="form-select" x-model="idModulo" @change="error = false" :class="error ? 'border border-danger' : ''">
<option value="">Selecciona una opción...</option>
<?php foreach ($modulosDisponibles as $m): ?>
<option value="<?= $m->id ?>"><?= $m->nombre_modulo ?></option>
<?php endforeach; ?>
</select>
<small class="text-danger" x-show="error">Debes seleccionar un módulo</small>
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

<!---------- MODAL AGREGAR SUBMODULO ---------->
<div class="modal fade"id="modalAgregarSubmodulo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
data-id-usuario="<?=$usuario->id ?>" x-data="submoduloUsuarioForm()">
<div class="modal-dialog modal-dialog-scrollable modal-md">
<div class="modal-content">

<div class="modal-header bg-primary">
<h5 class="modal-title text-white">Asignar submódulos (<?=$usuario->nombre?>)</h5>
<button type="button"class="btn-close btn-close-white" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">
<input type="hidden" id="idModuloPrincipal">

<div class="mb-3">
<label class="form-label fw-semibold">Módulo / submódulo donde se asignará:</label>
<input type="text" class="form-control" id="nombreModuloPadre" disabled>
</div>

<div class="mb-3">
<label class="form-label fw-semibold">Nombre del submódulo:</label>
<select class="form-select"x-model="idModulo" @change="error = false" :class="error ? 'border border-danger' : ''">
<option value="">Selecciona una opción...</option>
<?php foreach ($modulosDisponibles as $m): ?>
<option value="<?= $m->id ?>"><?= $m->nombre_modulo ?></option>
<?php endforeach; ?>
</select>
<small class="text-danger" x-show="error">Debes seleccionar un submódulo</small>
</div>

</div>

<!-- FOOTER -->
<div class="modal-footer">
<button type="button"class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" :disabled="enviando" @click="resetForm()">
Cancelar
</button>

<button class="btn btn-success" @click="guardar()" :disabled="enviando">
<span x-show="!enviando">Guardar</span>
<span x-show="enviando">Guardando...</span>
</button>
</div>

</div>
</div>
</div>

<!---------- MODAL EDITAR PERMISOS ---------->
<div class="modal fade"
id="modalEditarPermisos"
tabindex="-1"
data-bs-backdrop="static"
data-bs-keyboard="false"
x-data="permisosForm()"
@editar-permisos.window="abrirEditar($event.detail)">

<div class="modal-dialog modal-md">
<div class="modal-content">

<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
Editar permisos del módulo / submódulo
</h5>
<button type="button"
class="btn-close btn-close-white"
data-bs-dismiss="modal"
@click="resetForm()">
</button>
</div>

<div class="modal-body">

<!-- Nombre -->
<div class="mb-3">
<label class="form-label fw-semibold">
Nombre del módulo / submódulo:
</label>
<input type="text"
class="form-control"
x-model="nombreModulo"
disabled>
</div>

<!-- Loader -->
<div class="text-center my-3" x-show="cargando">
<div class="spinner-border text-primary"></div>
</div>

<!-- Permisos -->
<div x-show="!cargando">

<label class="form-label fw-semibold">
Permisos del módulo / submódulo:
</label>

<div class="form-check mb-2">
<input class="form-check-input" type="checkbox" x-model="ver">
<label class="form-check-label">Ver</label>
</div>

<div class="form-check mb-2">
<input class="form-check-input" type="checkbox" x-model="descargar">
<label class="form-check-label">Descargar</label>
</div>

<div class="form-check mb-2">
<input class="form-check-input" type="checkbox" x-model="agregar">
<label class="form-check-label">Agregar</label>
</div>

<div class="form-check mb-2">
<input class="form-check-input" type="checkbox" x-model="editar">
<label class="form-check-label">Editar</label>
</div>

<div class="form-check mb-2">
<input class="form-check-input" type="checkbox" x-model="eliminar">
<label class="form-check-label">Eliminar</label>
</div>

</div>

</div>

<div class="modal-footer">

<button type="button"
class="btn bg-danger-subtle text-danger"
data-bs-dismiss="modal"
@click="resetForm()"
:disabled="enviando">
Cancelar
</button>

<button class="btn btn-success"
@click="guardar()"
:disabled="enviando || cargando">

<span x-show="!enviando">Guardar</span>
<span x-show="enviando">Guardando...</span>

</button>

</div>

</div>
</div>
</div>

</div>