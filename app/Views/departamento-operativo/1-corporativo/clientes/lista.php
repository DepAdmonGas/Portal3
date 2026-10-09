<div id="clientes-lista-empty-message" class="row mt-4 mb-5"<?php if ($idEstacion): ?> style="display:none"<?php endif; ?>>
<div class="col-12">
<div class="alert alert-info text-center">
    <i class="ti ti-info-circle fs-4"></i>
    Debes de seleccionar una estación del menú superior para poder visualizar la información de la Lista de Clientes.
</div>
</div>
</div>
<div id="clientes-lista-content"<?php if (!$idEstacion): ?> style="display:none"<?php endif; ?>>
<div id="container" class="mt-4 mb-4"
data-id-estacion="<?= $idEstacion ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-dia="<?= $idDia ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-es-direccion-operaciones="<?= $esDireccionOperaciones ? 'true' : 'false' ?>"
data-module-station-key="corte-diario"
x-data="clientesListaComponent()">

<div class="text-center py-5" x-show="loading">
<div class="spinner-border text-primary" role="status"></div>
<p class="mt-2 text-muted">Cargando lista de clientes...</p>
</div>
<div class="row mb-2 d-flex justify-content-end">
    <div class="col-12 col-md-auto d-grid mb-3">
<button type="button" class="btn bg-primary-subtle text-primary" @click="abrirModalCrear()">
<i class="ti ti-plus"></i> Nuevo
</button>
</div>
</div>



<div class="row " x-show="!loading">

<div class="col-12">
<div class="alert alert-success py-2 px-3 mb-4" role="alert">
<div class="row text-center">
<div class="col"><b>CC:</b> Carta de Crédito</div>
<div class="col"><b>AC:</b> Acta Constitutiva</div>
<div class="col"><b>CD:</b> Comprobante de Domicilio</div>
<div class="col"><b>ID:</b> Identificación</div>
<div class="col"><b>OC:</b> Opinión de Cumplimiento</div>
<div class="col"><b>NP:</b> Poder Notarial</div>
</div>
</div>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-4">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-2" >
<table id="tablaCredito" class="table table-striped table-bordered  text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-4">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-2" >
<table id="tablaDebito" class="table table-striped table-bordered text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>
<!-----------------modal nuevo cliente-------------->

<div class="modal fade" id="Modal" tabindex="-1" x-ref="modalCrear" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
<i class="ti ti-user-plus"></i>    
Nuevo Cliente</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<h6 class="form-label">* Cuenta:</h6> 
<textarea class="form-control rounded-0 mt-1 mb-4" id="Cuenta" x-model="formCuenta"></textarea>

<h6 class="form-label">* Cliente:</h6> 
<textarea class="form-control rounded-0 mt-1 mb-4" id="Cliente" x-model="formCliente"></textarea>

<h6 class="form-label">* Tipo:</h6> 
<select class="form-select rounded-0 mt-1 mb-4" id="Tipo" x-model="formTipo">
<option value="">Selecciona una opción...</option>
<option value="Crédito">Crédito</option>
<option value="Débito">Débito</option>
</select>

<div id="SelCredito" x-cloak x-show="formTipo === 'Crédito'">

<h6 class="mb-1 form-label">RFC:</h6> 
<input type="text" class="form-control rounded-0  mb-2" id="RFC" x-model="formRfc">
<input type="file" class="form-control mb-3" id="ConstanciaRFC" @change="files[4] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Carta de crédito:</h6> 
<input type="file" class="form-control mb-3" id="CartaCredito" @change="files[0] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Acta constitutiva:</h6> 
<input type="file" class="form-control mb-3" id="ActaConstitutiva" @change="files[1] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Comprobante de domicilio:</h6> 
<input type="file" class="form-control  mb-3" id="ComprobanteDom" @change="files[2] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Identificación:</h6> 
<input type="file" class="form-control  mb-3" id="Identificacion" @change="files[3] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Poder Notarial:</h6> 
<input type="file" class="form-control  mb-3" id="PoderNotarial" @change="files[5] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Opinión de Cumplimiento:</h6>
<input type="file" class="form-control  mb-3" id="OpinionCumplimiento" @change="files[6] = $event.target.files[0] || null">
</div>

<div id="SelDebito" x-cloak x-show="formTipo === 'Débito'">

<h6 class="mb-1 form-label">RFC:</h6> 
<input type="text" class="form-control rounded-0 mb-2" id="RFC" x-model="formRfc">
<input type="file" class="form-control mb-3" id="ConstanciaRFC" @change="files[4] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Comprobante de domicilio:</h6> 
<input type="file" class="form-control mb-3" id="ComprobanteDom" @change="files[2] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Identificación:</h6> 
<input type="file" class="form-control mb-3" id="Identificacion" @change="files[3] = $event.target.files[0] || null">
</div>
</div>


<div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
    <div class="col-12 col-md-auto d-grid mb-2 mb-md-0 me-0 me-md-2">
    
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i>
Cancelar</button>
</div>

<div class="col-12 col-md-auto d-grid">
    
<button type="button" class="btn btn-labeled2 btn-success" @click="guardarCrear()" :disabled="guardandoCrear">
<span x-show="!guardandoCrear">
<i class="ti ti-check"></i>    
Guardar
</span>
<span x-show="guardandoCrear"><span class="spinner-border spinner-border-sm me-1"></span> Guardando...</span>
</button>
</div>
</div>
</div>


</div>
</div>
</div>

<!-----------------modal editar cliente-------------->
<div class="modal fade" id="ModalEditar" tabindex="-1" x-ref="modalEditar" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
    <i class="ti ti-edit"></i>
Editar Cliente</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<h6 class="mb-1 form-label">* Cuenta:</h6> 
<textarea class="form-control rounded-0 mb-3" id="EditCuenta" x-model="editCuenta"></textarea>

<h6 class="mb-1 form-label">* Cliente:</h6> 
<textarea class="form-control rounded-0 mb-3" id="EditCliente" x-model="editCliente"></textarea>

<h6 class="mb-1 form-label">* Tipo:</h6> 
<select class="form-select rounded-0 mb-3" id="EditTipo" x-model="editTipo" disabled>
<option value="Crédito">Crédito</option>
<option value="Débito">Débito</option>
</select>

<div x-cloak x-show="editTipo === 'Crédito'">
<h6 class="mb-1 form-label">RFC:</h6> 
<input type="text" class="form-control mb-2 rounded-0" id="EditRFC_Credito" x-model="editRfcCredito">
<input type="file" class="form-control  mb-3" id="EditRFCDoc_Credito" @change="editFiles[4] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Carta de crédito:</h6> 
<input type="file" class="form-control mb-3" id="EditCartaCredito" @change="editFiles[0] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Acta constitutiva:</h6> 
<input type="file" class="form-control mb-3" id="EditActaConstitutiva" @change="editFiles[1] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Comprobante de domicilio:</h6> 
<input type="file" class="form-control  mb-3" id="EditComprobanteDom_Credito" @change="editFiles[2] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Identificación:</h6> 
<input type="file" class="form-control  mb-3" id="EditIdentificacion_Credito" @change="editFiles[3] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Opinión de Cumplimiento:</h6>
<input type="file" class="form-control  mb-3" id="EditOpinion" @change="editFiles[5] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Poder Notarial:</h6> 
<input type="file" class="form-control mb-3" id="EditPoder" @change="editFiles[6] = $event.target.files[0] || null">
</div>

<div x-cloak x-show="editTipo === 'Débito'">

<h6 class="mb-1 form-label">RFC:</h6> 
<input type="text" class="form-control mb-2" id="EditRFC_Debito" x-model="editRfcDebito">
<input type="file" class="form-control  mb-3" id="EditRFCDoc_Debito" @change="editFiles[4] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Comprobante de domicilio:</h6> 
<input type="file" class="form-control mb-3" id="EditComprobanteDom_Debito" @change="editFiles[2] = $event.target.files[0] || null">

<h6 class="mb-1 form-label">Identificación:</h6> 
<input type="file" class="form-control mb-3" id="EditIdentificacion_Debito" @change="editFiles[3] = $event.target.files[0] || null">
</div>
</div>

<!--------------footer-------->
<div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
    <div class="col-12 col-md-auto d-grid mb-2 mb-md-0 me-0 me-md-2">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i>
Cancelar</button>
    </div>


<div class="col-12 col-md-auto d-grid">
<button type="button" class="btn btn-labeled2 btn-success" @click="guardarEditar()" :disabled="guardandoEditar">

<span x-show="!guardandoEditar">
<i class="ti ti-check"></i>    
Guardar</span>
<span x-show="guardandoEditar"><span class="spinner-border spinner-border-sm me-1"></span> Guardando...</span>
</button>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
