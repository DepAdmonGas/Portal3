<div id="container" class="mt-4 mb-4">
<div class="row">

<!---------- POLIZA DE SEGURO ---------->
<div class="col-lg-6 col-12">
<div class="card">
<div class="card-body">

<div class="float-end">
<?= 
'<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPolizaSeguro">
    <i class="ti ti-plus"></i> Nuevo
</button>' 
?>     
</div>

<h4 class="card-title mb-0">Póliza de Seguro</h4>
<div class="datatables mt-4">
<div class="table-responsive">
<table id="table-poliza" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>

</div>
</div>
</div>


<!---------- POLIZA DE SEGURO (COBERTURA) ---------->
<div class="col-lg-6 col-12">
<div class="card">
<div class="card-body">

<div class="float-end">
<?= 
'<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPolizaCobertura">
    <i class="ti ti-plus"></i> Nuevo
</button>' 
?>     
</div>

<h4 class="card-title mb-0">Cobertura de la Póliza de Seguro</h4>
<div class="datatables mt-4">
<div class="table-responsive">
<table id="table-poliza-cobertura" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>

</div>
</div>
</div>

</div>
</div>

<!---------- MODAL - POLIZA DE SEGURO ---------->
<div class="modal fade" id="modalPolizaSeguro" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...polizaForm() }">

<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header">
<h4 class="modal-title">Agregar póliza de seguro</h4>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">

<!-- POLIZA DE SEGURO -->
<label class="form-label">* Póliza de seguro:</label>
<input type="file" class="form-control" x-ref="poliza" @change="handleFile($event)" :class="errors.poliza ? 'is-invalid' : ''">
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

<!---------- MODAL - POLIZA DE SEGURO (COBERTURA) ---------->
<div class="modal fade" id="modalPolizaCobertura" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...coberturaForm() }">

<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header">
<h4 class="modal-title">Agregar cobertura de póliza de seguro</h4>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">

<!-- COBERTURA DE POLIZA DE SEGURO -->
<label class="form-label">* Cobertura de póliza de seguro:</label>
<input type="file" class="form-control" x-ref="cobertura" @change="handleFile($event)" :class="errors.cobertura ? 'is-invalid' : ''">
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
