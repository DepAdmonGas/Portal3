<div id="container" class="mt-4 mb-5"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-es-encargado="<?= $esEncargado ? 'true' : 'false' ?>"
data-nombre-puesto="<?= $nombrePuesto ?>"
data-module-station-key="organigrama"
x-data="{ ...actions(), ...organigramaComponent() }">

<div x-show="!idEstacionActual">
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación o departamento del menú superior para poder visualizar el organigrama.
</div>
</div>

<div x-show="idEstacionActual">
<div>


<div class="row">

<!---------- IMAGEN DEL ORGANIGRAMA ---------->
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<div class="card h-100">

<div class="card-header bg-primary d-flex justify-content-between align-items-center">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-photo text-white fs-6"></i>
<h5 class="mb-0 text-white">PREVISUALIZACIÓN</h5>
</div>

<template x-if="versionActual">
<span class="badge bg-light text-primary fw-semibold"><i class="ti ti-tag me-1"></i>Versión <span x-text="versionActual"></span></span>
</template>
</div>

<div class="card-body d-flex justify-content-center align-items-center" >
<template x-if="imagenUrl">
<div class="w-100 h-100 d-flex justify-content-center align-items-center">
<img :src="imagenUrl" alt="Organigrama" class="img-fluid" style="max-width:100%; max-height:100%; object-fit:contain;">
</div>
</template>

<template x-if="!imagenUrl">
<div class="w-100 h-100 d-flex flex-column justify-content-center align-items-center text-center">
<i class="ti ti-photo text-secondary mb-3" style="font-size:70px;"></i>
<h6 class="fw-semibold mb-1">Sin previsualización</h6>
<small class="text-muted">Selecciona una versión para visualizar el organigrama.</small>
</div>
</template>
</div>

</div>
</div>

<!---------- TABLA DE VERSIONES DEL ORGANIGRAMA ---------->
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<div class="card">

<div class="card-header bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white">
<i class="ti ti-table me-2"></i>VERSIONES DEL ORGANIGRAMA</h5>
<template x-if="puedeCrear">
<button type="button" class="btn btn-success" @click="abrirModalAgregar()"> <i class="ti ti-plus me-1"></i> Nuevo </button>
</template>
</div>
</div>

<div class="card-body">
<div class="datatables">
<div class="table-responsive">
<table id="tabla-organigrama-versions" class="table table-striped table-bordered mb-0 text-nowrap align-middle"></table>
</div>
</div>
</div>

</div>
</div>


<!---------- TABLA DE PLANTILLA DEL ORGANIGRAMA ---------->
<div class="col-12 mt-3">
<div class="card">

<div class="card-header bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-users me-2"></i>PLANTILLA DEL ORGANIGRAMA</h5>
<button type="button" class="btn btn-success" @click="agregarFilaPlantilla()" :disabled="!idEstacionActual">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</div>
</div>

<div class="card-body p-0">

<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 align-middle" id="tabla-plantilla">

<thead>
<tr>
<th class="text-center" width="24">#</th>
<th class="text-center">Descripción del puesto</th>
<th class="text-center">Nombre</th>
<th class="text-center" colspan="3">Perfil</th>
<th class="text-center" colspan="3">Contrato</th>
<th class="text-center" width="48px"><i class="ti ti-trash fs-6 text-danger"></i></th>
</tr>
</thead>

<tbody>
<template x-if="plantilla.length === 0">
<tr><td colspan="10" class="text-center text-secondary">No se encontro información</td></tr>
</template>

<template x-for="(row, idx) in plantilla" :key="row.id">
<tr>
<td class="text-center align-middle fw-bold" x-text="row.id"></td>
<td class="p-0">
<input type="text" class="form-control form-control p-3 border-0" x-model="row.descripcion" @change="actualizarCampoPlantilla(row.id, 'descripcion', row.descripcion)">
</td>
<td class="p-0">
<div class="position-relative">
<input type="text" class="form-control form-control p-3 border-0" x-model="row.nombre_completo" x-ref="nombreInput_$idx"
@input="buscarPersonal(row.idEstacion || idEstacionActual, idx, $event)" @change="asignarNombrePlantilla(row, idx)" :list="'personal-list-' + idx">
<datalist :id="'personal-list-' + idx">
<template x-for="p in resultadosPersonal[idx] || []" :key="p.id">
<option :value="p.nombre" :data-id="p.id"></option>
</template>
</datalist>
</div>
</td>


<!------------- PERFIL ------------>
<template x-if="row.documento_perfil">
<td class="text-center align-middle" width="98px">
<i class="ti ti-download fs-6 text-primary pointer" @click.prevent="download('organigrama-documentos', row.documento_perfil)"></i>
</td>
</template>

<template x-if="!row.documento_perfil">
<td class="text-center align-middle" colspan="3">
<i class="ti ti-upload fs-6 text-primary pointer" @click="abrirModalDocumento(row.id, 'perfil', false)"></i>
</td>
</template>

<template x-if="row.documento_perfil">
<td class="text-center align-middle" width="98px">
<i class="ti ti-pencil fs-6 text-warning pointer" @click.prevent="abrirModalDocumento(row.id, 'perfil', true)"></i>
</td>
</template>

<template x-if="row.documento_perfil">
<td class="text-center align-middle" width="98px">
<i class="ti ti-circle-x fs-6 text-danger pointer" @click.prevent="eliminarDocumento(row.id, 'perfil')"></i>
</td>
</template>

<!-------------  CONTRATO ------------>
<template x-if="row.documento_contrato">
<td class="text-center align-middle" width="98px">
<i class="ti ti-download fs-6 text-primary pointer" @click.prevent="download('organigrama-documentos', row.documento_contrato)"></i>
</td>
</template>

<template x-if="!row.documento_contrato">
<td class="text-center align-middle" colspan="3">
<i class="ti ti-upload fs-6 text-primary pointer" @click="abrirModalDocumento(row.id, 'contrato', false)"></i>
</td>
</template>

<template x-if="row.documento_contrato">
<td class="text-center align-middle" width="98px">
<i class="ti ti-pencil fs-6 text-warning pointer" @click.prevent="abrirModalDocumento(row.id, 'contrato', true)"></i>
</template>

<template x-if="row.documento_contrato">
<td class="text-center align-middle" width="98px">
<i class="ti ti-circle-x fs-6 text-danger pointer" @click.prevent="eliminarDocumento(row.id, 'contrato')"></i>
</td>
</template>

<td class="text-center align-middle">
<i class="ti ti-trash fs-6 text-danger pointer" @click="eliminarFilaPlantilla(row.id)"></i>
</td>

</tr>
</template>

</tbody>

</table>
</div>

</div>

</div>
</div>

</div>


<!---------- DATOS DE LA EMPRESA ---------->
<template x-if="stationInfoEstaciones.indexOf(idEstacionActual) !== -1 && stationInfo">
<div class="card mt-3">

<div class="card-header bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-building me-2"></i>DATOS DE LA EMPRESA</h5>
</div>
</div>

<div class="card-body p-0">

<div class="table-responsive">
<table class="table table-bordered table-striped mb-0 align-middle" id="tabla-datos-empresa">

<tbody>

<tr>
<th width="250px">Nombre de la empresa</th>
<td x-text="stationInfo.razonsocial"></td>
</tr>

<tr>
<th class="text-start align-middle">Registro Patronal</th>
<td class="p-0">
<input type="text" class="form-control border-0 rounded-0" x-model="stationInfo.registro_patronal" 
@change="actualizarStationInfo('registro_patronal', stationInfo.registro_patronal)">
</td>
</tr>

<tr>
<th class="text-start align-middle">Calle</th>
<td class="p-0">
<input type="text" class="form-control border-0 rounded-0" x-model="stationInfo.calle" @change="actualizarStationInfo('calle', stationInfo.calle)">
</td>
</tr>

<tr>
<th class="text-start align-middle">Número Ext.</th>
<td class="p-0">
<input type="text" class="form-control border-0 rounded-0" x-model="stationInfo.numero_exterior" @change="actualizarStationInfo('numero_exterior', stationInfo.numero_exterior)">
</td>
</tr>

<tr>
<th class="text-start align-middle">Número Int.</th>
<td class="p-0">
<input type="text" class="form-control border-0 rounded-0" x-model="stationInfo.numero_interior" @change="actualizarStationInfo('numero_interior', stationInfo.numero_interior)">
</td>
</tr>

<tr>
<th class="text-start align-middle">Colonia</th>
<td class="p-0">
<input type="text" class="form-control border-0 rounded-0" x-model="stationInfo.colonia" @change="actualizarStationInfo('colonia', stationInfo.colonia)">
</td>
</tr>

<tr>
<th class="text-start align-middle">Código Postal</th>
<td class="p-0">
<input type="text" class="form-control border-0 rounded-0" x-model="stationInfo.codigo_postal" @change="actualizarStationInfo('codigo_postal', stationInfo.codigo_postal)">
</td>
</tr>

<tr>
<th class="text-start align-middle">Estado</th>
<td class="p-0">
<input type="text" class="form-control border-0 rounded-0" x-model="stationInfo.estado" @change="actualizarStationInfo('estado', stationInfo.estado)">
</td>
</tr>

<tr>
<th class="text-start align-middle">Municipio</th>
<td class="p-0">
<input type="text" class="form-control border-0 rounded-0" x-model="stationInfo.municipio" @change="actualizarStationInfo('municipio', stationInfo.municipio)">
</td>
</tr>

<tr>
<th class="text-start align-middle">Número de teléfono</th>
<td class="p-0">
<input type="text" class="form-control border-0 rounded-0" x-model="stationInfo.numero_telefono" @change="actualizarStationInfo('numero_telefono', stationInfo.numero_telefono)">
</td>
</tr>

</tbody>
</table>

</div>
</div>

</div>
</template>

</div>
</div>

<div class="modal fade" id="modalAgregarOrganigrama" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Nuevo organigrama</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label">* Imagen (JPG, PNG):</label>
<input type="file" class="form-control" x-ref="fileArchivo" accept=".jpg,.jpeg,.png">
</div>
<div class="mb-3">
<label class="form-label">Observaciones:</label>
<textarea class="form-control" x-model="form.observaciones" rows="3"></textarea>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarOrganigrama()" :disabled="guardando">
<span x-text="guardando ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>
</div>
</div>
</div>

<div class="modal fade" id="modalDocumento" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" x-text="(documentoModo === 'editar' ? 'Editar documento' : 'Agregar documento') + ' (' + (documentoTipo === 'perfil' ? 'Perfil' : 'Contrato') + ')'"></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label">* Documento (PDF):</label>
<input type="file" class="form-control" x-ref="fileDocumento" accept=".pdf">
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarDocumento()" :disabled="guardando">Guardar</button>
</div>
</div>
</div>
</div>

</div>