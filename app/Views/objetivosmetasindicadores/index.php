<div id="container" x-data="{ ...actions(), ...objetivosMetasIndicadoresForm()}"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<div class="row mt-4">

<div class="col-12 col-md-6 d-flex align-items-stretch">
<div class="card w-100">

<div class="card-header text-bg-info">
<h4 class="mb-0 text-white card-title">
<i class="ti ti-label"> </i> OBJETIVO
</h4>
</div>

<div class="card-body">

<p class="card-text fs-4 fw-normal">
Brindar a nuestros clientes una experiencia inigualable al cargar combustible o recibir alguno de nuestros servicios en cualquiera de nuestras sucursales del grupo Admongas.
</p>
</div>
</div>
</div>

<div class="col-12 col-md-6  align-items-stretch">
<div class="card w-100">

<div class="card-header text-bg-info">
<h4 class="mb-0 text-white card-title">   
<i class="ti ti-label"> </i> METAS
</h4> 
</div>

<div class="card-body">

<ul class="card-text fs-4 fw-normal">

<li><i class="ti ti-point"></i> Asegurar el bienestar de nuestros empleados utilizando siempre los mejores estándares de calidad.</li>
<li><i class="ti ti-point"></i> Mantener en excelentes condiciones la estación de servicio contando con personal
altamente capacitado tanto en operación como en mantenimiento.</li>
<li><i class="ti ti-point"></i> Atender peticiones de quejas y sugerencias por parte de los clientes. </li>
<li><i class="ti ti-point"></i> Cumplir con la legislación aplicable vigente.</li>
</ul>
</div>
</div>
</div>
</div>

<h4>INDICADORES</h4>

<div class="row mt-4">

<div class="col-md-4 d-flex align-items-stretch">

<div class="card w-100">
<a href="objetivos-metas-indicadores/capacitacion-personal">
<div class="card-body text-center">     
<i class="ti ti-chart-infographic fs-13"></i>   
<div class="fs-7 mt-3">
Capacitación del personal
</div>
</div>
</a>
</div>

</div>

<div class="col-md-4 d-flex align-items-stretch">
<div class="card w-100">
<a href="objetivos-metas-indicadores/experiencia-cliente">
<div class="card-body text-center">
<i class="ti ti-chart-infographic fs-13"></i>   
<div class="fs-7 mt-3">
Experiencia del cliente
</div>
</div>
</a>
</div>
</div>

<div class="col-md-4 d-flex align-items-stretch">
<div class="card w-100">
<a href="objetivos-metas-indicadores/indicador-ventas">
<div class="card-body text-center">
<i class="ti ti-chart-infographic fs-13"></i>   
<div class="fs-7 mt-3">
Ventas
</div>
</div>
</a>
</div>
</div>

</div>

<div class="row mt-3">

<div class="col-md-6">
<div class="card">
<div class="card-header">

<div class="d-flex align-items-center">
<h4 class="card-title mb-0">Seguimiento de objetivos y metas</h4>
<div class="ms-auto">

<div class="dropdown dropstart">
<a href="javascript:void(0)" class="btn btn-light text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
  <i class="ti ti-dots-vertical fs-4"></i>
</a>
<ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">

  <?= 
    !empty($permisos['crear']) ? 
    '<li>
      <a class="dropdown-item pointer" href="javascript:void(0)" @click="openNuevoObjetivoMetas()"><i class="ti ti-plus"></i> Agregar</a>
    </li>' 
    : '' 
  ?>   

  <?= 
    !empty($permisos['descargar']) ? 
    '<li>
      <a class="dropdown-item pointer" href="/sasisopa/objetivos-metas-indicadores/pdf-objetivos-metas"><i class="ti ti-download"></i> Descargar</a>
    </li>' 
    : '' 
  ?>   
                
</ul>
</div>       

</div>
</div>

</div>

<div class="card-body">


<div class="datatables">
<div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
<table id="table-seguimiento-objetivosmetas" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
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

<div class="col-md-6">
<div class="card">
<div class="card-header">
<div class="d-flex align-items-center">
<h4 class="card-title mb-0">Seguimiento y reporte de indicadores</h4>
<div class="ms-auto">

<div class="dropdown dropstart">
<a href="javascript:void(0)" class="btn btn-light text-dark" id="dropdownMenuButton " data-bs-toggle="dropdown" aria-expanded="false">
  <i class="ti ti-dots-vertical fs-4"></i>
</a>
<ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">

<?= 
    !empty($permisos['crear']) ? 
    '<li>
      <a class="dropdown-item pointer" href="javascript:void(0)" @click="openNuevoReporteIndicador()"><i class="ti ti-plus"></i> Agregar</a>
    </li>' 
    : '' 
  ?>   

  <?=
    !empty($permisos['descargar']) ? 
    '<li>
      <a class="dropdown-item pointer" href="/sasisopa/objetivos-metas-indicadores/pdf-reporte-indicadores"><i class="ti ti-download"></i> Descargar</a>
    </li>' 
    : '' 
  ?>   
              
</ul>
</div>       

</div>
</div>
</div>

<div class="card-body">


<div class="datatables">
<div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
<table id="table-seguimiento-indicadores" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
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
</div>
          
</div>
</div>
</div>
</div>


<!-- MODAL OBJETIVOS Y METAS-->
<div class="modal fade"
id="ObjetivosMetas"
x-ref="modalObjetivosMetas"
tabindex="-1">

<div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
<div class="modal-content">

    <!-- HEADER -->
    <div class="modal-header modal-colored-header bg-primary text-white">
        <h4 class="modal-title text-white">
            <label> 

            <i class="ti" :class = "mode === 'edit' ? 'ti-edit' : 
            mode === 'view' ? 'ti-eye' :
            'ti-target-arrow'"></i>
            
            <span x-text="
                mode === 'edit' ? 'Editar Seguimiento de objetivos y metas' :
                mode === 'view' ? 'Detalle de objetivos y metas' :
                'Seguimiento de objetivos y metas'">
              </span>


            </label>
        </h4>
        <button type="button"
                class="btn-close btn-close-white"
                data-bs-dismiss="modal"
                @click="$event.target.blur(); resetObjetivosMetas()">
        </button>
    </div>

    <!-- BODY -->
    <div class="modal-body">

    <div x-show="mode !== 'view'">
    <h6 class="fs-5">Satisfacción del cliente</h6>                

    <div class="row">
      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
        <label class="form-label">Fecha:</label>
        <input type="date" class="form-control" id="Dato1" x-model="objetivosMetas.satisfaccion.fecha">

        <label class="form-label mt-3">Medidas de acción para dar cumplimiento:</label>
        <textarea class="form-control" rows="1" id="Dato3" x-model="objetivosMetas.satisfaccion.accion"></textarea>
      </div>

      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
        <label class="form-label">Nivel de cumplimiento:</label>
        <input type="text" class="form-control" id="Dato2" x-model="objetivosMetas.satisfaccion.cumplimiento">

        <label class="form-label mt-3">Fecha de aplicación :</label>
        <input type="date" class="form-control" id="Dato4" x-model="objetivosMetas.satisfaccion.fecha_aplicacion">
      </div>
    </div>

    <hr>

    <h6 class="fs-5">Mantenimiento</h6>
                    
    <div class="row">
      
      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
        <label class="form-label">Fecha:</label>
        <input type="date" class="form-control" id="Dato5" x-model="objetivosMetas.mantenimiento.fecha">

      <label class="form-label mt-3">Medidas de acción para dar cumplimiento:</label>
        <textarea class="form-control" rows="1" id="Dato7" x-model="objetivosMetas.mantenimiento.accion"></textarea>
      </div>

      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
        <label class="form-label">Nivel de cumplimiento:</label>
        <input type="text" class="form-control" id="Dato6" x-model="objetivosMetas.mantenimiento.cumplimiento">

        <label class="form-label mt-3">fecha de aplicación :</label>
        <input type="date" class="form-control" id="Dato8" x-model="objetivosMetas.mantenimiento.fecha_aplicacion">
      </div>
    </div>
    <hr>


  <h6 class=" fs-5">Capacitación</h6>

  <div class="row">
      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
      <label class="form-label">Fecha:</label>
      <input type="date" class="form-control" id="Dato9" x-model="objetivosMetas.capacitacion.fecha">

      <label class="form-label mt-3">Medidas de acción para dar cumplimiento:</label>
      <textarea class="form-control" rows="1" id="Dato11" x-model="objetivosMetas.capacitacion.accion"></textarea>
    </div>


      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
      <label class="form-label">Nivel de cumplimiento:</label>
      <input type="text" class="form-control" id="Dato10" x-model="objetivosMetas.capacitacion.cumplimiento">

      <label class="form-label mt-3">fecha de aplicación :</label>
      <input type="date" class="form-control" id="Dato12" x-model="objetivosMetas.capacitacion.fecha_aplicacion">
    </div>
  </div>
  <hr>


  <h6 class=" fs-5">Quejas y sugerencias</h6>
  <div class="row">
      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
      <label class="form-label">Fecha:</label>
      <input type="date" class="form-control" id="Dato13" x-model="objetivosMetas.quejas.fecha">

      <label class="form-label mt-3">Medidas de acción para dar cumplimiento:</label>
      <textarea class="form-control" rows="1" id="Dato15" x-model="objetivosMetas.quejas.accion"></textarea>
    </div>


      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
      <label class="form-label">Nivel de cumplimiento:</label>
      <input type="text" class="form-control" id="Dato14" x-model="objetivosMetas.quejas.cumplimiento">

      <label class="form-label mt-3">fecha de aplicación:</label>
      <input type="date" class="form-control" id="Dato16" x-model="objetivosMetas.quejas.fecha_aplicacion">
    </div>
  </div>
  <hr>

  <h6 class=" fs-5">Cumplimiento de legislación </h6>
  <div class="row">
    

      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
      <label class="form-label">Fecha:</label>
      <input type="date" class="form-control" id="Dato17" x-model="objetivosMetas.legislacion.fecha">

      <label class="form-label mt-3">Medidas de acción para dar cumplimiento:</label>
      <textarea class="form-control" rows="1" id="Dato19" x-model="objetivosMetas.legislacion.accion"></textarea>
    </div>

      <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12"> 
      <label class="form-label">Nivel de cumplimiento:</label>
      <input type="text" class="form-control" id="Dato18" x-model="objetivosMetas.legislacion.cumplimiento">

      <label class="form-label mt-3">Fecha de aplicación :</label>
      <input type="date" class="form-control" id="Dato20" x-model="objetivosMetas.legislacion.fecha_aplicacion">
    </div>
  </div>
  </div>

  <div x-show="mode == 'view'">
    <div class="table-responsive overflow-x-auto overflow-y-hidden">
    
    
    <table class="table table-striped table-bordered mb-0 align-middle">
    <thead> 
    <tr>
    <th class="text-center align-middle">Fecha</th>
    <th class="text-center align-middle">Objetivo o meta</th>
    <th class="text-center align-middle">Nivel de cumplimiento</th>
    <th class="text-center align-middle">Medidas de acción para dar cumplimiento</th>
    <th class="text-center align-middle">Fecha de aplicación</th>
    </tr>
    </thead>

    <tbody>

      <template x-for="[key, item] in Object.entries(objetivosMetas)" :key="key">
          <tr>

              <td class="text-center align-middle"
                  x-text="item.fecha_formateada || 'S/I'">
              </td>

              <td class="text-center align-middle"
                  x-text="item.objetivo_meta || 'S/I'">
              </td>

              <td class="text-center align-middle"
                  x-text="item.cumplimiento || 'S/I'">
              </td>

              <td class="text-center align-middle"
                  x-text="item.accion || 'S/I'">
              </td>

              <td class="text-center align-middle"
                  x-text="item.fecha_aplicacion_formateada || 'S/I'">
              </td>

          </tr>
      </template>
  </tbody>
    </table>
    </div>

  </div>

    </div>

    <!-- FOOTER -->
    <div class="modal-footer" x-show="mode !== 'view'">

        <button type="button"
                class="btn bg-danger-subtle text-danger"
                data-bs-dismiss="modal"
                @click="resetObjetivosMetas()">
            <i class="ti ti-x"></i> Cancelar
        </button>

        <button type="button"
                class="btn btn-success"
                @click="$event.target.blur(); submitObjetivosMetas()"
                :disabled="loading">

                <i class="ti ti-check"></i>



            <span x-show="!loading">  
              <span x-text="
                mode === 'edit' ? 'Actualizar' : 'Guardar'">
              </span>
            </span>
            <span x-show="loading">Guardando...</span>

        </button>

    </div>

</div>
</div>
</div>

<!-- MODAL REPORTE DE INDICADORES-->
<div class="modal fade"
id="ReporteIndicadores"
x-ref="modalReporteIndicadores"
tabindex="-1">

<div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
<div class="modal-content">

    <!-- HEADER -->
    <div class="modal-header modal-colored-header bg-primary text-white">
      <h4 class="modal-title text-white">
      <label> 
        <i class="ti" :class= "mode === 'edit' ? 'ti-edit' :
        mode === 'view' ? 'ti-eye': 
        'ti-report'"></i>

        <span x-text="mode === 'edit' ? 'Editar Seguimiento y reporte de indicadores' : 
        mode === 'view' ? 'Detalle Seguimiento y reporte de indicadores' :
        'Seguimiento y reporte de indicadores'"></span>
      </label>
      
        
      </h4>
        <button type="button"
                class="btn-close btn-close-white"
                data-bs-dismiss="modal"
                @click="$event.target.blur(); resetReporteIndicadores()">
        </button>
    </div>

    <!-- BODY -->
    <div class="modal-body">

    <template x-if="mode !== 'view'">
    <div>
    <label class="form-label">* Fecha:</label>
    <input type="date" class="form-control" 
    x-model="reporteIndicadores.fecha"
    :class="errors.reporteIndicadores.fecha ? 'is-invalid' : ''"
    @input="errors.reporteIndicadores.fecha = false">

    <label class="form-label mt-3">* Capacitación:</label>
    <textarea class="form-control" 
    x-model="reporteIndicadores.capacitacion"
    :class="errors.reporteIndicadores.capacitacion ? 'is-invalid' : ''"
    @input="errors.reporteIndicadores.capacitacion = false"></textarea>

    <label class="form-label mt-3">* Experiencia del cliente:</label>
    <textarea class="form-control" 
    x-model="reporteIndicadores.experiencia"
    :class="errors.reporteIndicadores.experiencia ? 'is-invalid' : ''"
    @input="errors.reporteIndicadores.experiencia = false"></textarea>

    <label class="form-label mt-3">* Ventas:</label>
    <textarea class="form-control" 
    x-model="reporteIndicadores.ventas"
    :class="errors.reporteIndicadores.ventas ? 'is-invalid' : ''"
    @input="errors.reporteIndicadores.ventas = false"></textarea>

    <label class="form-label mt-3">* Medidas correctivas:</label>
    <textarea class="form-control" 
    x-model="reporteIndicadores.medidas"
    :class="errors.reporteIndicadores.medidas ? 'is-invalid' : ''"
    @input="errors.reporteIndicadores.medidas = false"></textarea>

    <label class="form-label mt-3">* Fecha de aplicación:</label>
    <input type="date" class="form-control" 
    x-model="reporteIndicadores.fecha_aplicacion"
    :class="errors.reporteIndicadores.fecha_aplicacion ? 'is-invalid' : ''"
    @input="errors.reporteIndicadores.fecha_aplicacion = false">
    </div>
    </template>

    <template x-if="mode === 'view'">
          <div>

              <label class="form-label">Fecha:</label>
              <div class="border rounded p-2 bg-light"
                x-text="reporteIndicadores.fecha_format || 'S/I'"></div>

              <label class="form-label mt-3">Capacitación:</label>
              <div class="border rounded p-2 bg-light" 
                x-text="reporteIndicadores.capacitacion || 'S/I'"></div>

              <label class="form-label mt-3">Experiencia del cliente:</label>
              <div class="border rounded p-2 bg-light" 
                x-text="reporteIndicadores.experiencia || 'S/I'"></div>

              <label class="form-label mt-3">Ventas:</label>
              <div class="border rounded p-2 bg-light" 
                x-text="reporteIndicadores.ventas || 'S/I'"></div>

              <label class="form-label mt-3">Medidas correctivas:</label>
              <div class="border rounded p-2 bg-light" 
                x-text="reporteIndicadores.medidas || 'S/I'"></div>

              <label class="form-label mt-3">Fecha de aplicación:</label>
              <div class="border rounded p-2 bg-light" 
                x-text="reporteIndicadores.fecha_aplicacion_format || 'S/I'"></div>

          </div>
    </template>

    </div>

    <!-- FOOTER -->
    <div class="modal-footer" x-show="mode !== 'view'">

        <button type="button"
                class="btn bg-danger-subtle text-danger"
                data-bs-dismiss="modal"
                @click="$event.target.blur(); resetReporteIndicadores()">
            <i class="ti ti-x"></i> Cancelar
        </button>

        <button type="button"
                class="btn btn-success"
                @click="submitReporteIndicadores()"
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

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
<div class="offcanvas-header">
<h5 class="offcanvas-title" id="offcanvasExampleLabel">
Bienvenido al elemento 4. OBJETIVOS, METAS E INDICADORES, del Sistema de Administración
</h5>
<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
</div>
<div class="offcanvas-body fs-4">

<p>
Aquí vas a poder consultar los objetivos y metas de la empresa, así como también visualizar las gráficas de los siguientes indicadores: Capacitación del personal, Experiencia del cliente y Ventas.
</p>

<hr>

<label class="fw-bold">Como hacerlo:</label>
<ul class="list-group list-group-flush">
<li class="list-group-item">Para evaluar la experiencia del cliente se tendrá que realizar cada 6 meses una encuesta de satisfacción (Dar clic en el botón PDF para descargarla e imprimirla)</li>
<li class="list-group-item">Se deberá coordinar para que en una semana se realicen el mayor número de encuestas a los clientes</li>
<li class="list-group-item">El resultado de cada una de las encuestas deberá ser vaciado en el apartado experiencia del cliente</li>
</ul>

<hr>

<label class="fw-bold">Responsables:</label>
<p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label>, <label class="text-danger fw-bold">Jefes de Piso</label> y <label class="text-danger fw-bold">Despachadores</label> obtener los resultados del indicador Experiencia del cliente, así como proponer medidas necesarias para el logro de objetivos, metas e indicadores.</p>

</div>
</div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->