<div id="container" class="mt-4 mb-4">

<div class="row">

<!---------- POLIZA DE SEGURO ---------->
<div class="col-lg-6 col-12">
<div class="card">
<div class="card-body">

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100 gap-2">
<h4 class="card-title mb-0">Póliza de Seguro</h4>
<div class="datatables mt-4">
<div class="table-responsive">
<table id="table-poliza" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>

<div class="d-grid d-md-block">
<?= 
'<button type="button" class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#modalPolizaSeguro">
    <i class="ti ti-plus"></i> Nuevo
</button>' 
?> 
</div>
</div>
    
</div>

</div>
</div>



<!---------- POLIZA DE SEGURO (COBERTURA) ---------->
<div class="col-lg-6 col-12">
<div class="card">
<div class="card-body">


<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100 gap-2">
<h4 class="card-title mb-0">Cobertura de la Póliza de Seguro</h4>
<div class="datatables">
<div class="table-responsive">
<table id="table-poliza-cobertura" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
<div class="d-grid d-md-block">
<?= 
'<button type="button" class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#modalPolizaCobertura">
    <i class="ti ti-plus"></i> Nuevo
</button>' 
?> 
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
<div class="modal-header bg-primary">
<h4 class="modal-title text-white">
    <i class="ti ti-shield-plus"></i>
    Agregar póliza de seguro</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">

<!-- POLIZA DE SEGURO -->
<label class="form-label">* Póliza de seguro:</label>
<input type="file" class="form-control" x-ref="poliza" @change="handleFile($event)" :class="errors.poliza ? 'is-invalid' : ''">
</div>

<!-- FOOTER -->
<div class="modal-footer">

<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                   <button type="button" 
class="btn bg-danger-subtle 
text-danger" data-bs-dismiss="modal" 
@click="resetForm()">
<i class="ti ti-x"></i>
Cancelar</button>   
                    </div>
                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button" 
class="btn btn-success" 
@click="submit()" 
:disabled="loading">

<i class="ti ti-check"></i>
<span x-show="!loading">Guardar</span>
<span x-show="loading">Guardando...</span>
</button>
                    </div>
                  </div>
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
<div class="modal-header bg-primary">
<h4 class="modal-title text-white">
<i class="ti ti-file-text-shield"></i>    
Agregar cobertura de póliza de seguro</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">

<!-- COBERTURA DE POLIZA DE SEGURO -->
<label class="form-label">* Cobertura de póliza de seguro:</label>
<input type="file" class="form-control" x-ref="cobertura" @change="handleFile($event)" :class="errors.cobertura ? 'is-invalid' : ''">
</div>

<!-- FOOTER -->
<div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                      <button type="button" 
class="btn bg-danger-subtle text-danger" 
data-bs-dismiss="modal" 
@click="resetForm()">
<i class="ti ti-x"></i>
Cancelar</button>
                    </div>
                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button"
    class="btn btn-success"
    @click="submit()"
    :disabled="loading">

    <i class="ti ti-check"></i>

    <span x-show="!loading">Guardar</span>
    <span x-show="loading">Guardando...</span>
</button>
                    </div>
                  </div>





</div>

</div>
</div>
</div>

</div>
