<div id="container" data-elemento="2" data-herramienta="1">

<div class="row mt-4">
  <div class="col-md-4 d-flex align-items-stretch">
    <div class="card w-100">
      <div class="card-body">

        <h4 class="card-title">Identificación y evaluación de Aspectos e Impactos Ambientales.</h4>

        <div class="text-end">
          <a href="identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/aspectos-ambientales-pdf" type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-download"></i>
            Descargar
          </a>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-4 d-flex align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Identificación y evaluación de Riesgos y Peligros para registrar el análisis.</h4>

        <div class="text-end">
          <a href="identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales/riesgos-peligros-pdf" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-download"></i>
            Descargar
          </a>
        </div>

      </div>
    </div>
  </div>
</div>

<div class="row">
<div class="col-md-6">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Análisis de Riesgo del Sector Hidrocarburos (ARSH)</h4>
  </div>

  <div class="datatables mt-3">
    <div class="table-responsive">
      <table id="table-lista-analisis-riesgo" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
          <th>#</th>
          <th>Fecha</th>
          <th>Descripción</th>
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

      <div class="float-end">
      <div x-data="{ ...actions(), ...listaasistenciaForm() }">
        <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn btn-primary" @click="crearAsistencia()">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
        ?>   
      </div>  
    </div>

  <h4 class="card-title mb-0">Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</h4>

    <div class="datatables mt-4">
    <div class="table-responsive">
      <table id="table-lista-asistencia" class="table table-bordered table-striped mb-0 text-nowrap align-middle">
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

<div class="modal fade"
     id="anexos"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     x-data="{ ...actions(), ...anexosForm() }"
     @open-edit.window="getEdit($event.detail)">

    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">Análisis de riesgo Anexos</h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

          <!-- LOADING -->
          <div x-show="loading" class="text-center py-3">
              Cargando...
          </div>

          <!-- CONTENIDO -->
          <div x-show="!loading">

              <div class="pb-2">
                  <div style="font-size: 1.2em;">
                      <b>Fecha:</b> <span x-text="fecha"></span>
                  </div>

                  <div style="font-size: 1.2em;">
                      <b>Descripción:</b> <span x-text="descripcion"></span>
                  </div>
              </div>

              <table class="table table-bordered table-striped table-hover">
                  <thead>
                      <tr>
                          <th>Descripción</th>
                          <th width="32"><i class="ti ti-download fs-6 text-muted"></i></th>
                      </tr>
                  </thead>

                  <tbody>

                      <!-- SI HAY DATOS -->
                      <template x-if="anexos.length > 0">
                          <template x-for="anexo in anexos" :key="anexo.id">
                              <tr>
                                  <td x-text="anexo.descripcion"></td>
                                  <td class="text-center">
                                      <a @click="download('analisis-riesgo', anexo.documento)">
                                          <i class="ti ti-download text-danger fs-6"></i>
                                      </a>
                                  </td>
                              </tr>
                          </template>
                      </template>

                      <!-- SIN DATOS -->
                      <template x-if="anexos.length === 0">
                          <tr>
                              <td colspan="2" class="text-center text-muted">
                                  No se encontró información para mostrar.
                              </td>
                          </tr>
                      </template>

                  </tbody>
              </table>

          </div>

      </div>

            <!-- FOOTER -->
            <div class="modal-footer">

                <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                    <i class="ti ti-x"></i> Cancelar
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
            Bienvenido al elemento 2. IDENTIFICACIÓN DE PELIGROS Y ASPECTOS AMBIENTALES, ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p >
           En este apartado podrás consultar las matrices para la identificación de aspectos e impactos ambientales así 
           como la de Riesgos y peligros dela estación de servicio.
          </p>

          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item disabled">Da clic en recuadro Identificación y evaluación de Aspectos e Impactos Ambientales para visualizar la matriz </li>
            <li class="list-group-item disabled">Da clic en el recuadro Identificación y evaluación de Riesgos y Peligros para registrar el análisis para visualizar la matriz</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> dar a conocer los aspectos ambientales significativos a todo el personal de la estación de servicio puede ser mediante trípticos, capacitaciones o enviando comunicados mediante el elemento numero 7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA.</p>

          <small>Nota:<br>
          Recuerda que para aquellos riesgos y peligros significativos se deben generar e implementar medidas de mitigación.
          </small>
         
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->