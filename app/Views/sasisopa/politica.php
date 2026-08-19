<div id="container" data-elemento="1" data-herramienta="1">


<div class="text-end mt-2">
<div class="btn-group">
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>
<ul class="dropdown-menu animated rubberBand">

<?= 
!empty($permisos['editar']) ? 
'<li><a class="dropdown-item pointer" data-bs-toggle="modal" data-bs-target="#editar"><i class="ti ti-pencil"></i> Editar Politica</a></li>' 
: '' 
?>

<?= 
!empty($permisos['descargar']) ? 
'<li><a class="dropdown-item pointer" href="/sasisopa/politica/pdf" target="_blank"><i class="ti ti-file-download"></i> Descargar Politica</a></li>' 
: '' 
?>

</ul>
</div>
</div>

<div class="row mt-2">

<!-- POLITICA -->
<div class="col-md-4 align-items-stretch">
<div class="card w-100">
<div class="card-header text-bg-info">
<h4  class="mb-0 text-white card-title">
<i class="ti ti-label"></i> Política
</h4>
</div>
<div class="card-body">
<p id="politica_text"
class="card-text fs-4 fw-normal"
data-politica="<?= htmlspecialchars($user->estacion->politica ?? '') ?>">

<?= htmlspecialchars($user->estacion->politica ?? '') ?>
</p>

</div>
</div>
</div>

<!-- MISION -->
<div class="col-md-4 align-items-stretch">
<div class="card w-100">
<div class="card-header text-bg-info">
<h4 class="mb-0 text-white card-title"> 
<i class="ti ti-label"></i> Misión
</h4>
</div>
<div class="card-body">
<p id="mision_text"
class="card-text fs-4 fw-normal"
data-mision="<?= htmlspecialchars($user->estacion->mision ?? '') ?>">

<?= htmlspecialchars($user->estacion->mision ?? '') ?>
</p>
</div>
</>
</div>
</div>

<!-- VISION -->
<div class="col-md-4 align-items-stretch">
<div class="card w-100">
<div class="card-header text-bg-info">
<h4 class="mb-0 text-white card-title">
<i class="ti ti-label"></i> Visión
</h4>
</div>
<div class="card-body">
<p id="vision_text"
class="card-text fs-4 fw-normal"
data-vision="<?= htmlspecialchars($user->estacion->vision ?? '') ?>">

<?= htmlspecialchars($user->estacion->vision ?? '') ?>
</p>

</div>
</div>
</div>

</div>


<div class="row">

<!-------------------- CARD DE Fo.ADMONGAS.001 (Lista de comprobación) ---------------------->
<div class="col-md-6">
<div class="card">
<div class="card-header">
<div class="float-end">
<?= 
!empty($permisos['crear']) ? 

'<button type="button" class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#listaComprobacion" >
<i class="ti ti-plus"></i> Nuevo
</button>' 
: '' 
?>     
</div>

<h4 class="card-title mb-0">Fo.ADMONGAS.001 (Lista de comprobación)</h4>

</div>
<div class="card-body pb-0">

<div class="datatables">

<div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
<table id="table-lista-comprobacion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>

<tr>
<th>#</th>
<th>Fecha</th>
<th class="text-center">
<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
</th>
</tr>

</thead>
<tbody></tbody>
</table>
</div>


                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>


</div>
</div>

</div>



<!-------------------- CARD DE Fo.ADMONGAS.010 ---------------------->
<div class="col-md-6">
<div class="card">

  <div class="card-header">

<div class="float-end">
<div x-data="{ ...actions(), ...listaasistenciaForm() }">
<?= 
!empty($permisos['crear']) ? 
'<button type="button" class="btn bg-primary-subtle text-primary"  @click="crearAsistencia()">
<i class="ti ti-plus"></i> Nuevo
</button>' 
: '' 
?>   

</div>  
</div>

<h4 class="card-title mb-0">Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</h4>


  </div>


<div class="card-body pb-0">



<div class="datatables">
<div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
<table id="table-lista-asistencia" class="table table-bordered table-striped  text-nowrap align-middle">
<thead>
<tr>
<th>#</th>
<th>Fecha</th>
<th>Estatus</th>
<th class="text-center">
<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>

</div>
</div>
</div>

</div>


</div>

<!-- -------------------------- -->
<!-- -------------------------- -->

<div class="modal fade"

id="editar"
tabindex="-1"
data-bs-backdrop="static"
data-bs-keyboard="false"
x-data="{ ...actions(), ...politicaForm() }">


<div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
<div class="modal-content">


<!-- HEADER -->
<div class="modal-header modal-colored-header bg-primary text-white">
<h4 class="modal-title text-white"><i class="ti ti-scale"></i>


Editar 1. POLÍTICA</h4>
<button type="button"
class="btn-close btn-close-white"
data-bs-dismiss="modal">
</button>
</div>


<!-- BODY -->
<div class="modal-body">

<!-- Politica -->
<label class="form-label">Política:</label>
<textarea class="form-control"
rows="6"
x-model="politica"></textarea>

<!-- Mision -->
<label class="form-label mt-3">Misión:</label>
<textarea class="form-control"
rows="6"
x-model="mision"></textarea>


<!-- Vision -->
<label class="form-label mt-3">Visión:</label>
<textarea class="form-control"
rows="6"
x-model="vision"></textarea>

</div>


<!-- FOOTER -->
<div class="modal-footer">

<button type="button"
class="btn bg-danger-subtle text-danger"
data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cancelar
</button>

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

<div class="modal fade"

id="listaComprobacion"
tabindex="-1"
data-bs-backdrop="static"
data-bs-keyboard="false"
x-data="{ ...actions(), ...listacomprobacionForm() }"
@open-edit.window="getEdit($event.detail)">


<div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header modal-colored-header bg-primary text-white">
<h4 class="modal-title text-white">


<label>
<i class="ti" :class="mode === 'create' ? 'ti-list-check' : 'ti-edit'"></i>
<span x-text="mode === 'create' ? 'Lista de comprobación' : 'Editar lista de comprobación'"></span>
</label>
</h4>


<button type="button"
class="btn-close btn-close-white"
data-bs-dismiss="modal"
@click="resetModal()">
</button>
</div>


<!-- BODY -->
<div class="modal-body">


<!-- FECHA -->
<label class="form-label">Fecha:</label>
<input type="date"
class="form-control"
x-model="fecha"
:class="errors.fecha ? 'is-invalid' : ''">


<!-- TABLA -->
<table class="table table-bordered table-sm mt-3">
<thead>
<tr>
<th class="text-center">Criterio</th>
<th class="text-center">Resultado</th>
</tr>
</thead>
<tbody>

<!-- FILAS -->
<template x-for="(label, key) in criterios" :key="key">
<tr>
<td class="align-middle" x-text="label"></td>
<td class="p-0 align-middle">
<select class="form-control rounded-0 border-0"
x-model="respuestas[key]"
:class="errors[key] ? 'is-invalid' : ''">
<option value="">Selecciona una opción...</option>
<option value="Si">Si</option>
<option value="En Parte">En Parte</option>
<option value="No">No</option>
</select>
</td>
</tr>
</template>

<!-- ASISTENTES -->
<tr>
<td colspan="2" class="p-2">
<label class="form-label">Asistentes:</label>
<textarea class="form-control"
x-model="asistentes"></textarea>
</td>
</tr>


<!-- COMENTARIOS -->
<tr>
<td colspan="2" class="p-2">
<label class="form-label">Comentarios:</label>
<textarea class="form-control"
x-model="comentarios"></textarea>
</td>
</tr>

</tbody>
</table>

</div>

<!-- FOOTER -->
<div class="modal-footer">

<button type="button"
class="btn bg-danger-subtle text-danger"
data-bs-dismiss="modal"
@click="resetModal()">
<i class="ti ti-x"></i> Cancelar
</button>

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

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">

<div class="offcanvas-header">
<h5 class="offcanvas-title" id="offcanvasExampleLabel">
Bienvenido al elemento 1. POLITICA, del Sistema de Administración
</h5>
<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
</div>
<div class="offcanvas-body fs-4">

<p>
Aquí vas a encontrar la política de tu empresa acorde a los requisitos solicitados en las Disposiciones 
Administrativas de Carácter General <b>(DACG)</b>, Sistemas de Administración de Seguridad Industrial, 
Seguridad Operativa y Protección al Medio Ambiente <b>(SASISOPA)</b>.
</p>
<p>
La política debe ser comunicada a todo el personal incluyendo clientes, prestadores de servicios y proveedores.
</p>

<hr>

<label class="fw-bold">Como hacerlo:</label>
<ul class="list-group list-group-flush">
<li class="list-group-item disabled">Elegir un día a la semana para comunicar política en una plática de 5 minutos</li>
<li class="list-group-item disabled">Imprimir y colocar en el tablón de anuncios de la estación</li>
<li class="list-group-item disabled">Subirla a la página web (en caso de contar)</li>
<li class="list-group-item disabled">Elaborar trípticos y distribuirlos entre los empleados</li>
</ul>

<hr>

<label class="fw-bold">Responsables:</label>
<p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> y <label class="text-danger fw-bold">Jefes de Piso</label>, comunicar la política a todas las partes interesadas.</p>

</div>



</div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->