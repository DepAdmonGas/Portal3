<div id="container" x-data="{ ...actions(), ...objetivosMetasIndicadoresForm()}">

<div class="row mt-4">
  <div class="col-md-6 d-flex align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title text-primary">OBJETIVO</h4>
          <p class="card-text fs-4 fw-normal">
            Brindar a nuestros clientes una experiencia inigualable al cargar combustible o recibir alguno de nuestros servicios en cualquiera de nuestras sucursales del grupo Admongas.
          </p>
      </div>
    </div>
  </div>

  <div class="col-md-6 d-flex align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title text-primary">METAS</h4>
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

<h5>INDICADORES</h5>

<div class="row mt-4">

  <div class="col-md-4 d-flex align-items-stretch">
    
    <div class="card w-100">
      <a href="objetivos-metas-indicadores/capacitacion-personal">
      <div class="card-body">        
        <div class="fs-6 text-center">
        Capacitación del personal
        </div>
      </div>
      </a>
    </div>
    
  </div>

  <div class="col-md-4 d-flex align-items-stretch">
    <div class="card w-100">
      <a href="objetivos-metas-indicadores/experiencia-cliente">
      <div class="card-body">
        <div class="fs-6 text-center">
          Experiencia del cliente
        </div>
      </div>
      </a>
    </div>
  </div>

   <div class="col-md-4 d-flex align-items-stretch">
    <div class="card w-100">
      <a href="objetivos-metas-indicadores/indicador-ventas">
      <div class="card-body">
        <div class="fs-6 text-center">
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
    <div class="card-body">

    <div class="d-flex align-items-center">
      <h4 class="card-title mb-0">Seguimiento de objetivos y metas</h4>
        <div class="ms-auto">

         <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="link text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots fs-7"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">

              <?= 
                !empty($permisos['crear']) ? 
                '<li>
                  <a class="dropdown-item" href="javascript:void(0)" @click="openNuevoObjetivoMetas()"><i class="ti ti-plus"></i> Agregar</a>
                </li>' 
                : '' 
              ?>   

               <?= 
                !empty($permisos['descargar']) ? 
                '<li>
                  <a class="dropdown-item" href="/sasisopa/objetivos-metas-indicadores/pdf-objetivos-metas"><i class="ti ti-download"></i> Descargar</a>
                </li>' 
                : '' 
              ?>   
                            
            </ul>
          </div>       
        
        </div>
    </div>

    <div class="datatables mt-3">
      <div class="table-responsive">
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
    <div class="card-body">

    <div class="d-flex align-items-center">
      <h4 class="card-title mb-0">Seguimiento y reporte de indicadores</h4>
        <div class="ms-auto">

         <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="link text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots fs-7"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">

             <?= 
                !empty($permisos['crear']) ? 
                '<li>
                  <a class="dropdown-item" href="javascript:void(0)" @click="openNuevoReporteIndicador()"><i class="ti ti-plus"></i> Agregar</a>
                </li>' 
                : '' 
              ?>   

              <?=
                !empty($permisos['descargar']) ? 
                '<li>
                  <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-download"></i> Descargar</a>
                </li>' 
                : '' 
              ?>   
                           
            </ul>
          </div>       
        
        </div>
    </div>

    <div class="datatables mt-3">
      <div class="table-responsive">
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

        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">

                <!-- HEADER -->
                <div class="modal-header">
                    <h4 class="modal-title"
                        x-text="
                            mode === 'edit' ? 'Editar Seguimiento de objetivos y metas' :
                            mode === 'view' ? 'Detalle de objetivos y metas' :
                            'Seguimiento de objetivos y metas'
                        ">
                    </h4>
                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            @click="$event.target.blur(); resetObjetivosMetas()">
                    </button>
                </div>

                <!-- BODY -->
                <div class="modal-body">

                <div x-show="mode !== 'view'">
                <h6 class="text-primary fs-5">Satisfacción del cliente</h6>                

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
   
                <h6 class="text-primary fs-5">Mantenimiento</h6>
                                
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

  
              <h6 class="text-primary fs-5">Capacitación</h6>
            
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

  
              <h6 class="text-primary fs-5">Quejas y sugerencias</h6>
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
 
              <h6 class="text-primary fs-5">Cumplimiento de legislación </h6>
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
                <div class="table-responsive">
                <table class="table table-sm table-bordered">
                <thead> 
                <tr>
                <th class="text-center align-middle bg-primary text-white">Fecha</th>
                <th class="text-center align-middle bg-primary text-white">Objetivo o meta</th>
                <th class="text-center align-middle bg-primary text-white">Nivel de cumplimiento</th>
                <th class="text-center align-middle bg-primary text-white">Medidas de acción para dar cumplimiento</th>
                <th class="text-center align-middle bg-primary text-white">fecha de aplicación</th>
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
                        Cancelar
                    </button>

                    <button type="button"
                            class="btn btn-success"
                            @click="submitObjetivosMetas()"
                            :disabled="loading">

                        <span x-show="!loading">Guardar</span>
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

        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">

                <!-- HEADER -->
                <div class="modal-header">
                    <h4 class="modal-title" x-text="mode === 'edit' ? 'Editar Seguimiento y reporte de indicadores' : 'Seguimiento y reporte de indicadores'"></h4>
                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            @click="resetModal()">
                    </button>
                </div>

                <!-- BODY -->
                <div class="modal-body">

                  

                </div>

                <!-- FOOTER -->
                <div class="modal-footer">

                    <button type="button"
                            class="btn bg-danger-subtle text-danger"
                            data-bs-dismiss="modal"
                            @click="resetModal()">
                        Cancelar
                    </button>

                    <button type="button"
                            class="btn btn-success"
                            @click="submitObjetivosMetas()"
                            :disabled="loading">

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