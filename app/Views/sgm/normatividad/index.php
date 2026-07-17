<div id="container" data-elemento="105" data-herramienta="2" data-id="0">

<div class="row mt-4">
<div class="col-md-8">

<div x-data="{ ...actions(), ...inventarioNormatividad() }">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Fo.SGM.005 Inventario de Normatividad Aplicable</h4>
      <div class="ms-auto">
        
       <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="link text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots fs-7"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item" href="javascript:void(0)" @click="nuevo()"><i class="ti ti-plus"></i> Agregar</a>
              </li>
              <li>
                <a class="dropdown-item" href="/sgm/normatividad-aplicable-mediciones/inventario-normatividad/pdf"><i class="ti ti-download"></i> Descargar</a>
              </li>
              <li>
                <a class="dropdown-item" href="/sasisopa/mejores-practicas-estandares"><i class="ti ti-file"></i> Mejores Prácticas y Estándares</a>
              </li>
            </ul>
          </div>   
      
      </div>
  </div>

  <div class="datatables mt-3">
    <div class="table-responsive">
      <table id="table-inventario-normatividad" class="table table-sm table-striped table-bordered align-middle">
        <thead>
          <tr>
          <th class="text-center align-middle" style="max-width:100px;">Norma, acuerdo, disposición</th>
          <th class="text-center align-middle" style="max-width:100px;">Fecha de publicación</th>
          <th class="text-center align-middle" style="max-width:100px;">Fecha de aplicación</th>
          <th class="text-center align-middle" style="max-width:200px;">Equipo o procedimiento de medición al que aplica</th>
          <th class="text-center align-middle" style="max-width:100px;">Link</th>
          <th class="text-center align-middle">
          <a class="text-muted"><i class="ti ti-trash fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
  

  </div>
</div>

<div
    class="modal fade"
    id="modalNormatividad"
    tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white">
                    Operación y mantenimiento
                </h5>
                <button
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>
            </div>
            <div class="modal-body">

                <div class="mb-3">

                    <label class="form-label">
                        Norma, acuerdo o disposición
                    </label>

                    <textarea
                        class="form-control"
                        x-model="form.norma"
                        rows="3"
                        @change="errors.norma = false"
                        :class="errors.norma ? 'is-invalid' : ''"
                    ></textarea>

                </div>

                <div class="row">

                    <div class="col-md-6">

                        <label class="form-label">
                            Fecha de publicación
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            x-model="form.fecha_publicacion"
                            @change="errors.fecha_publicacion = false"
                        :class="errors.fecha_publicacion ? 'is-invalid' : ''"
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Fecha de aplicación
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            x-model="form.fecha_aplicacion"
                        >

                    </div>

                </div>

                <div class="mt-3">

                    <label class="form-label">
                        Equipo o procedimiento
                    </label>

                    <textarea
                        class="form-control"
                        rows="3"
                        x-model="form.equipo"
                        @change="errors.equipo = false"
                        :class="errors.equipo ? 'is-invalid' : ''"
                    ></textarea>

                </div>

                <div class="mt-3">

                    <label class="form-label">
                        Link
                    </label>

                    <textarea
                        class="form-control"
                        rows="2"
                        x-model="form.link"
                        @change="errors.link = false"
                        :class="errors.link ? 'is-invalid' : ''"
                    ></textarea>

                </div>

            </div>

            <div class="modal-footer">

            <button type="button"
            class="btn bg-danger-subtle text-danger"
            data-bs-dismiss="modal">
            <i class="ti ti-x"></i> Cancelar
            </button>

                <button
                    class="btn btn-success"
                    @click="guardarNormatividad()"
                >
                  <i class="ti ti-check"></i> Guardar
                </button>

            </div>

        </div>

    </div>

</div>

</div>
</div>

<div class="col-md-4">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Fo.SGM.006 Requisitos legales del SGM</h4>
      <div class="ms-auto">
      </div>
  </div>

  <div class="mt-1 text-end">

          <a class="btn bg-danger-subtle text-danger mt-2" 
          href="/sgm/normatividad-aplicable-mediciones/pdf-requisito-legal" download>
          <i class="ti ti-download"></i> Descargar</a>

          <a class="btn bg-info-subtle text-info mt-2" 
          href="/sgm/normatividad-aplicable-mediciones/requisito-legal-sgm">
          <i class="ti ti-file"></i> Requisitos Legales SASISOPA</a>
  </div>
                    
  </div>
</div>

<div class="card">
  <div x-data="{ ...actions(), ...listaasistenciaForm() }">
  <div class="card-body">

    <div class="float-end">
        <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn btn-primary" @click="crearAsistencia()">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
        ?>    
    </div>

    <h4 class="card-title mb-0">Fo.SGM.001 Lista de asistencia</h4>

  <div class="datatables mt-4">
    <div class="table-responsive">
      <table id="table-lista-asistencia" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
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

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Ayuda
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

         <p><b>Bienvenido al elemento 5. Normatividad aplicable a mediciones</b>, en este elemento de manera anual deveras verificar si la legislación en materia de Mediciones se ha actualizado o han surgido nuevas normas o disposiciones a cumplir, dicha información tienes que registrarla en el formato 005
          En el formato 006 de manera anual verifica que los requisitos legales a los que estas sujeto en Materia de Gestión de Medición, se encuentren vigentes.</p>
          <p>Por ultimo no olvides que una vez que realices o complementes los registros debes dar a conocer a todo el personal la lista de normatividad a la que estamos sujetos y la lista de permisos con la que debemos contar, regístralo en el formato 001.
          </p>
          
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

</div>