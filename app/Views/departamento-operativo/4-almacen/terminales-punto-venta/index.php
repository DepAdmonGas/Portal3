<?php
/**
 * Terminales Punto de Venta — Almacén
 *
 * La vista no contiene lógica de negocio ni ciclos: los catálogos y los permisos
 * viajan como data-attributes y se pintan con Alpine. La estación NO se pide en
 * los modales: se toma del contexto del módulo (ModuleStationService).
 *
 * La columna "Estación" sólo aparece cuando el contexto está en
 * "Todas las estaciones"; los usuarios fijados a una estación (sin selector)
 * nunca la ven.
 *
 * @var string $title
 * @var string $moduleStationKey
 * @var bool   $estacionFija
 * @var int    $estacionActual
 * @var bool   $esUsuarioEstacion
 * @var bool   $puedeCrear
 * @var bool   $puedeEditar
 * @var bool   $puedeEliminar
 * @var bool   $puedeDescargar
 */

$baseUrl = '/departamento-operativo/almacen/terminales-punto-venta';

// Catálogos de los dos campos tipo lista. Se pintan desde el servidor para que
// las <option> ya existan en el DOM cuando Alpine inicializa el <select>: con
// x-for el x-model no alcanzaba a fijar el valor y los selects se veían vacíos.
$catalogos = ['estatus_tpv' => [], 'tipo_tpv' => []];

if (!empty($catalogosJson)) {
    $decodificado = json_decode((string)$catalogosJson, true);
    if (is_array($decodificado)) {
        $catalogos = array_merge($catalogos, $decodificado);
    }
}
?>

<div id="container"
class="pb-4"
data-base-url="<?= $baseUrl ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-fija="<?= !empty($estacionFija) ? 'true' : 'false' ?>"
data-estacion-actual="<?= (int)($estacionActual ?? 0) ?>"
data-es-usuario-estacion="<?= !empty($esUsuarioEstacion) ? 'true' : 'false' ?>"
data-puede-usar-selector="<?= !empty($puedeUsarSelector) ? 'true' : 'false' ?>"
data-catalogos="<?= htmlspecialchars($catalogosJson ?? '{}', ENT_QUOTES, 'UTF-8') ?>"
data-puede-crear="<?= !empty($puedeCrear) ? 'true' : 'false' ?>"
data-puede-editar="<?= !empty($puedeEditar) ? 'true' : 'false' ?>"
data-puede-eliminar="<?= !empty($puedeEliminar) ? 'true' : 'false' ?>"
data-puede-eliminar-registro="<?= !empty($puedeEliminarRegistro) ? 'true' : 'false' ?>"
data-puede-descargar="<?= !empty($puedeDescargar) ? 'true' : 'false' ?>"
x-data="{ ...actions(), ...terminalesPuntoVentaComponent() }">

<div class="row mt-3">

<!-- ENCABEZADO -->
<div class="col-12 mb-3">
<template x-if="puedeUsarSelector">
<button type="button" class="btn bg-warning-subtle text-warning float-end" @click="irAFallasTPV()">
<i class="ti ti-alert-triangle me-1"></i> Fallas TPV
</button>
</template>
<template x-if="puedeCrear && estacionFija">
<button type="button" class="btn bg-primary-subtle text-primary float-end me-1" @click="abrirNuevo()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</template>
</div>

<!-- TABLA -->
<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-terminales-punto-venta" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead></thead>
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>

<!-- ================= MODAL NUEVO ================= -->
<div class="modal fade" id="modalNuevo" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-device-desktop-plus text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Nueva Terminal</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label mb-1">* TPV:</label>
<input type="text"
class="form-control"
x-model="form.tpv"
:style="errores.tpv ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.tpv = false">
</div>
<div class="col-md-6">
<label class="form-label mb-1">* No. de serie:</label>
<input type="text"
class="form-control"
x-model="form.no_serie"
:style="errores.no_serie ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.no_serie = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Modelo / Marca:</label>
<input type="text"
class="form-control"
x-model="form.modelo"
:style="errores.modelo ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.modelo = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">No. de lote:</label>
<input type="text"
class="form-control"
x-model="form.no_lote">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Tipo de conexión:</label>
<input type="text"
class="form-control"
x-model="form.tipo_conexion"
:style="errores.tipo_conexion ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.tipo_conexion = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* No. de afiliación:</label>
<input type="text"
class="form-control"
x-model="form.no_afiliacion"
:style="errores.no_afiliacion ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.no_afiliacion = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Teléfono de atención:</label>
<input type="text"
class="form-control"
x-model="form.telefono"
:style="errores.telefono ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.telefono = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Activas:</label>
<input type="text"
class="form-control"
x-model="form.estado"
:style="errores.estado ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.estado = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Rollos:</label>
<input type="text"
class="form-control"
x-model="form.rollos"
:style="errores.rollos ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.rollos = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Cargadores:</label>
<input type="text"
class="form-control"
x-model="form.cargadores"
:style="errores.cargadores ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.cargadores = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Pedestales en buen estado:</label>
<input type="text"
class="form-control"
x-model="form.pedestales"
:style="errores.pedestales ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.pedestales = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* No. de impresiones:</label>
<input type="number"
class="form-control"
min="0"
x-model="form.no_impresiones"
:style="errores.no_impresiones ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.no_impresiones = false">
</div>


<div class="col-md-6">
<label class="form-label mb-1">Condición:</label>
<select class="form-select"
x-ref="selEstatusTpv"
x-model="form.estatus_tpv"
>
<option value="">Seleccione una opción...</option>
<template x-for="op in catalogos.estatus_tpv" :key="op">
<option :value="op" x-text="op"></option>
</template>
</select>
</div>

<div class="col-md-6">
<label class="form-label mb-1">Tipo de TPV:</label>
<select class="form-select"
x-ref="selTipoTpv"
x-model="form.tipo_tpv"
>
<option value="">Seleccione una opción...</option>
<template x-for="op in catalogos.tipo_tpv" :key="op">
<option :value="op" x-text="op"></option>
</template>
</select>
</div>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
<button type="button" class="btn btn-success" @click="guardarNuevo()" :disabled="guardando">
<template x-if="!guardando"><i class="ti ti-check me-1"></i></template>
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardando ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>
</div>
</div>
</div>

<!-- ================= MODAL EDITAR ================= -->
<div class="modal fade" id="modalEditar" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-pencil text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Editar Terminal (#<span class="fw-semibold" x-text="detalle ? detalle.id : ''"></span>)</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<template x-if="cargandoDetalle">
<div class="d-flex flex-column align-items-center justify-content-center py-5">
<div class="spinner-border text-primary mb-3" role="status"></div>
<span class="text-muted fw-semibold">Consultando la información del registro...</span>
</div>
</template>

<template x-if="!cargandoDetalle && detalle">
<div>
<div class="row g-3">
<div class="col-md-6">
<label class="form-label mb-1">* TPV:</label>
<input type="text"
class="form-control"
x-model="form.tpv"
:style="errores.tpv ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.tpv = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* No. de serie:</label>
<input type="text"
class="form-control"
x-model="form.no_serie"
:style="errores.no_serie ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.no_serie = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Modelo / Marca:</label>
<input type="text"
class="form-control"
x-model="form.modelo"
:style="errores.modelo ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.modelo = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">No. de lote:</label>
<input type="text"
class="form-control"
x-model="form.no_lote">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Tipo de conexión:</label>
<input type="text"
class="form-control"
x-model="form.tipo_conexion"
:style="errores.tipo_conexion ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.tipo_conexion = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* No. de afiliación:</label>
<input type="text"
class="form-control"
x-model="form.no_afiliacion"
:style="errores.no_afiliacion ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.no_afiliacion = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Teléfono de atención:</label>
<input type="text"
class="form-control"
x-model="form.telefono"
:style="errores.telefono ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.telefono = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Activas:</label>
<input type="text"
class="form-control"
x-model="form.estado"
:style="errores.estado ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.estado = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Rollos:</label>
<input type="text"
class="form-control"
x-model="form.rollos"
:style="errores.rollos ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.rollos = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Cargadores:</label>
<input type="text"
class="form-control"
x-model="form.cargadores"
:style="errores.cargadores ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.cargadores = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Pedestales en buen estado:</label>
<input type="text"
class="form-control"
x-model="form.pedestales"
:style="errores.pedestales ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.pedestales = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* No. de impresiones:</label>
<input type="number"
class="form-control"
min="0"
x-model="form.no_impresiones"
:style="errores.no_impresiones ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.no_impresiones = false">
</div>


<div class="col-md-6">
<label class="form-label mb-1">Condición:</label>
<select class="form-select"
x-ref="selEstatusTpv"
x-model="form.estatus_tpv"
>
<option value="">Seleccione una opción...</option>
<template x-for="op in catalogos.estatus_tpv" :key="op">
<option :value="op" x-text="op"></option>
</template>
<option value="__LEGACY__" x-show="form.estatus_tpv && !esOpcionEstatusTpv(form.estatus_tpv)" x-text="form.estatus_tpv"></option>
</select>
</div>

<div class="col-md-6">
<label class="form-label mb-1">Tipo de TPV:</label>
<select class="form-select"
x-ref="selTipoTpv"
x-model="form.tipo_tpv"
>
<option value="">Seleccione una opción...</option>
<template x-for="op in catalogos.tipo_tpv" :key="op">
<option :value="op" x-text="op"></option>
</template>
<option value="__LEGACY__" x-show="form.tipo_tpv && !esOpcionTipoTpv(form.tipo_tpv)" x-text="form.tipo_tpv"></option>
</select>
</div>
</div>
</div>
</template>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
<button type="button"
class="btn btn-success"
@click="guardarEdicion()"
:disabled="guardando || cargandoDetalle">
<template x-if="!guardando"><i class="ti ti-check me-1"></i></template>
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardando ? 'Guardando...' : 'Actualizar'"></span>
</button>
</div>
</div>
</div>
</div>

<!-- ================= MODAL DETALLE ================= -->
<div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-eye text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Detalle de Terminal (#<span class="fw-semibold" x-text="detalleRegistro ? detalleRegistro.id : ''"></span>)</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<template x-if="cargandoRegistro">
<div class="d-flex flex-column align-items-center justify-content-center py-5">
<div class="spinner-border text-primary mb-3" role="status"></div>
<span class="text-muted fw-semibold">Consultando la información del registro...</span>
</div>
</template>

<template x-if="!cargandoRegistro && detalleRegistro">
<div>
<div class="row g-3">
<div class="col-md-6">
<label class="form-label mb-1">Estación:</label>
<div x-text="detalleRegistro.estacion_nombre"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">TPV:</label>
<div x-text="detalleRegistro.tpv"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">No. de serie:</label>
<div x-text="detalleRegistro.no_serie"></div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">Modelo:</label>
<div x-text="detalleRegistro.modelo"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">No. de lote:</label>
<div x-text="detalleRegistro.no_lote"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Tipo de conexión:</label>
<div x-text="detalleRegistro.tipo_conexion"></div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">No. de afiliación:</label>
<div x-text="detalleRegistro.no_afiliacion"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Teléfono:</label>
<div x-text="detalleRegistro.telefono"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Estado:</label>
<div x-text="detalleRegistro.estado"></div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">Rollos:</label>
<div x-text="detalleRegistro.rollos"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Cargadores:</label>
<div x-text="detalleRegistro.cargadores"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Pedestales:</label>
<div x-text="detalleRegistro.pedestales"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">No. de impresiones:</label>
<div x-text="detalleRegistro.no_impresiones_label"></div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">Condición:</label>
<div>
<span x-text="detalleRegistro.estatus_tpv"></span>
</div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Tipo de TPV:</label>
<div x-text="detalleRegistro.tipo_tpv"></div>
</div>

<div class="col-12">
<h5 class="text-primary fw-semibold mb-2">Reparaciones anteriores (finalizadas)</h5>
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">No. de serie</th>
<th class="text-center align-middle">Falla</th>
<th class="text-center align-middle">Día de solución</th>
<th class="text-center align-middle">Costo</th>
<th class="text-center align-middle">Responsable</th>
</tr>
</thead>
<tbody>
<template x-for="r in detalleRegistro.reparaciones" :key="r.id">
<tr>
<td class="text-center align-middle" x-text="r.no_serie"></td>
<td class="text-center align-middle" x-text="r.falla"></td>
<td class="text-center align-middle" x-text="r.dia_solucion"></td>
<td class="text-center align-middle" x-text="r.costo"></td>
<td class="text-center align-middle" x-text="r.responsable"></td>
</tr>
</template>
<template x-if="!detalleRegistro.reparaciones || detalleRegistro.reparaciones.length === 0">
<tr><td colspan="5" class="text-center text-primary">No hay registro de reparaciones anteriores.</td></tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>
</template>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
</div>
</div>
</div>
</div>
</div>
