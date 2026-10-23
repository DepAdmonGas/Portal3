<div id="container" class="mt-4 mb-4" data-puede-administrar="<?= $puedeAdministrarProcedimientos ? 'true' : 'false' ?>" x-data="{ ...actions(), ...procedimientosAdminComponent() }">
<div class="row">

<!---------- ACTIVIDADES TECNICAS ---------->
<div class="col-lg-6 col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="fs-4 ti ti-clipboard-list"></i> Actividades Técnicas</h5>
<button type="button" class="btn btn-success btn-procedimientos-agregar d-none" data-tipo="actividades-tecnicas" @click="abrirAgregar('actividades-tecnicas', '#table-actividades-tecnicas')"><i class="ti ti-plus me-1"></i>Nuevo</button>
</div>
</div>
<div class="card-body">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-hidden pb-4">
<table id="table-actividades-tecnicas" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead><tr><th>#</th><th>Nombre de la actividad</th><th><i class="ti ti-dots-vertical fs-6"></i></th></tr></thead>
<tbody></tbody>
</table>
</div>
</div> 
</div>
</div>
</div>
 
<!---------- VISITAS A LA ESTACION ---------->
<div class="col-lg-6 col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="fs-4 ti ti-eye"></i> Visitas a la Estación</h5>
<button type="button" class="btn btn-success btn-procedimientos-agregar d-none" data-tipo="visita-estacion" @click="abrirAgregar('visita-estacion', '#table-visitas-estacion')"><i class="ti ti-plus me-1"></i>Nuevo</button>
</div>
</div>
<div class="card-body">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-hidden pb-4">
<table id="table-visitas-estacion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead><tr><th>#</th><th>Nombre de la visita</th><th><i class="ti ti-dots-vertical fs-6"></i></th></tr></thead>
<tbody></tbody>
</table>
</div>
</div>  
</div>
</div>
</div>
</div>

<div class="modal fade" id="modal-procedimiento" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="modal-procedimiento-titulo" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white" id="modal-procedimiento-titulo"><i class="ti ti-clipboard-text me-1"></i><span x-text="modoProcedimiento === 'crear' ? 'Nuevo procedimiento' : 'Editar procedimiento'"></span></h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<form id="form-procedimiento" x-ref="formProcedimiento" enctype="multipart/form-data" novalidate @submit.prevent="guardarProcedimiento()">
<div class="modal-body">
<input type="hidden" name="tipo" x-ref="procedimientoTipo">
<input type="hidden" name="id" x-ref="procedimientoId">
<div class="row g-3">
<div class="col-12">
<label for="procedimiento-nombre" class="form-label">* Nombre:</label>
<input type="text" class="form-control" id="procedimiento-nombre" name="nombre_a" maxlength="255" required
:class="erroresProcedimiento.nombre_a ? 'is-invalid' : ''" x-ref="procedimientoNombre"
@input="erroresProcedimiento.nombre_a = ''">
<div class="invalid-feedback" x-show="erroresProcedimiento.nombre_a" x-text="erroresProcedimiento.nombre_a"></div>
</div>
<div class="col-12">
<label for="procedimiento-archivo" class="form-label" x-text="modoProcedimiento === 'crear' ? '* Archivo PDF:' : 'Archivo PDF (opcional):'"></label>
<input type="file" class="form-control" id="procedimiento-archivo" name="archivo" accept="application/pdf,.pdf"
:required="modoProcedimiento === 'crear'" :class="erroresProcedimiento.archivo ? 'is-invalid' : ''"
x-ref="procedimientoArchivo" @change="erroresProcedimiento.archivo = ''">
<div class="invalid-feedback" x-show="erroresProcedimiento.archivo" x-text="erroresProcedimiento.archivo"></div>
<div class="form-text">PDF de hasta 25 MB. En edición, deja el campo vacío para conservar el archivo actual.</div>
</div>
</div>
</div>
<div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
<div class="col-12 col-md-auto d-grid">
<button type="button" class="btn bg-danger-subtle text-danger me-0 me-md-2" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancelar</button>
</div>
<div class="col-12 col-md-auto d-grid">
<button type="submit" class="btn btn-success mt-1 mt-md-0" :disabled="loading">
<span class="spinner-border spinner-border-sm me-1" x-show="loading"></span>
<i class="ti ti-check me-1" x-show="!loading"></i>Guardar
</button>
</div>
</div>
</div>
</form>
</div>
</div>
</div>
</div>
