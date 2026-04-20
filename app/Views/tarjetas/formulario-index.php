<div class="mb-4" x-data="{ ...actions(), ...seguimientoForm() }"
x-init="init()" id="container" data-estacion="<?= $idEstacion ?>" data-seguimiento="<?= $noSolicitud ?>" data-puesto="<?= $utilitiesUser['idPuestoUser'] ?>">
  
<div class="row mt-3 mb-3">
<div class="col-8"> <span class="badge rounded-pill bg-success">No. de Solicitud: <?=$noSolicitud?></span></div>

<div class="col-4 d-flex justify-content-end align-items-center gap-2">
<div class="text-end">
<div class="btn-group">
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>
<ul class="dropdown-menu animated rubberBand pointer">
<?= !empty($permisos['crear']) ? '<li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo"> <i class="ti ti-plus"></i> Nuevo </a></li>' : '' ?>
<div id="botonDescargaFile"></div>
<div id="botonSeguimiento"></div>
</ul>
</div>
</div>
</div>

</div>

<div class="datatables">
<div class="table-responsive">
<table id="table-tarjetas-formulario" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>     

</div>


<!---------- MODAL AGREGAR TARJETAS ---------->
<div class="modal fade" id="nuevo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...tarjetasForm() }" @open-edit.window="openEdit($event.detail)" data-estacion-modal="<?= $idEstacion ?>" data-solicitud="<?= $noSolicitud ?>">

<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header">
<h4 class="modal-title" x-text="mode === 'create' ? 'Crear registro' : 'Editar registro'"></h4>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">

<!-- RAZON SOCIAL -->
<label class="form-label">* Razon social:</label>
<input type="text" class="form-control mb-3" x-model="razon_social" @input="errors.razon_social = false" :class="errors.razon_social ? 'is-invalid' : ''">

<!-- NOMBRE USUARIO -->
<label class="form-label">* Usuario:</label>
<input type="text" class="form-control mb-3" x-model="nombre_usuario" @input="errors.nombre_usuario = false" :class="errors.nombre_usuario ? 'is-invalid' : ''">

<!-- VEHICULO -->
<label class="form-label">* Vehiculo:</label>
<input type="text" class="form-control mb-3" x-model="vehiculo" @input="errors.vehiculo = false" :class="errors.vehiculo ? 'is-invalid' : ''">

<!-- PLACAS -->
<label class="form-label">* Placas:</label>
<input type="text" class="form-control mb-3" x-model="placas" @input="errors.placas = false" :class="errors.placas ? 'is-invalid' : ''">

<!-- NO. UNIDAD -->
<label class="form-label">* No. Unidad:</label>
<input type="text" class="form-control mb-3" x-model="no_unidad" @input="errors.no_unidad = false" :class="errors.no_unidad ? 'is-invalid' : ''">

<!-- TARJETA -->
<label class="form-label">* Tarjeta:</label>
<input type="text" class="form-control mb-3" x-model="tarjeta" @input="errors.tarjeta = false" :class="errors.tarjeta ? 'is-invalid' : ''">

<!-- TIPO DE TARJETA -->
<label class="form-label">* Tipo de tarjeta:</label>
<select class="form-select mb-3" x-model="tipo_tarjeta" @change="errors.tipo_tarjeta = false":class="errors.tipo_tarjeta ? 'is-invalid' : ''">
<option value="">Selecciona una opción...</option>
<option value="Cliente Nuevo">Cliente Nuevo</option>
<option value="Tarjeta Adicional">Tarjeta Adicional</option>
<option value="Desgaste">Desgaste</option>
<option value="Reposición por extravio $50.00">Reposición por extravio $50.00</option>
</select>

</div>

<!-- FOOTER -->
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" @click="resetForm()">Cancelar</button>
<button type="button" class="btn btn-success" @click="submit()" :disabled="loading">
<span x-show="!loading">Guardar</span>
<span x-show="loading">Guardando...</span>
</button>
</div>

</div>
</div>
</div>

