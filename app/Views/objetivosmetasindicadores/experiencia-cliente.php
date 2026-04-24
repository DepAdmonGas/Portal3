<div id="container" class="mb-3" x-data="{ ...actions(), ...experienciaCliente() }">
  <div class="text-end mt-2">
      <div class="btn-group">
          <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
             <i class="ti ti-dots-vertical fs-4"></i>
          </button>
            <ul class="dropdown-menu animated rubberBand">
              <li><a class="dropdown-item"  @click="nuevo()"><i class="ti ti-plus"></i> Agregar</a></li>
              <?= 
                !empty($permisos['descargar']) 
                ? '<li>
                    <a class="dropdown-item" 
                      @click="download(\'encuestas\', \'Formato encuestas.pdf\')">
                        <i class="ti ti-download"></i> Descargar
                    </a>
                  </li>' 
                : '' 
                ?>
            </ul>
      </div>
  </div>

  <div class="row mt-3">

    <div class="col-md-8 col-sm-12">

      <div class="datatables mt-3">
        <div class="table-responsive">
          <table id="table-experiencia-cliente" class="table table-sm table-striped table-bordered mb-0 text-nowrap align-middle">
            <thead>
              <tr>
                  <th colspan="3"></th>
                  <th colspan="2" class="text-center text-primary">Excelente</th>
                  <th colspan="2" class="text-center text-success">Bueno</th>
                  <th colspan="2" class="text-center text-warning">Regular</th>
                  <th colspan="2" class="text-center text-danger">Malo</th>
                  <th colspan="2"></th>
              </tr>
              <tr>
              <th>#</th>
              <th>Fecha</th>            
              <th>Encuestados</th>
              <th>Resultado</th>
              <th>%</th>
              <th>Resultado</th>
              <th>%</th>
              <th>Resultado</th>
              <th>%</th>
              <th>Resultado</th>
              <th>%</th>
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
    <div class="col-md-4 col-sm-12">
          <div id="chart"></div>
    </div>
  </div>

</div>


<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            INDICADORES DE VENTAS
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
           La satisfacción del cliente nos permite identificar que tan bien estamos logrando los objetivos de la empresa, 
           así como también nos permite identificar áreas de oportunidad para mejorar. </b>
          </p>
          <p>
            En la parte superior derecha encontraras el menu desplegable para descargar la encuesta de satisfacción del cliente, misma que deberás descargar, imprimir y otorgar a los despachadores para que a su vez se le dé la opción al cliente de llenar la encuesta de satisfacción.
            La encuesta deberá realizarse dos veces al año durante una semana en todos los turnos, una vez terminadas las encuestas deberás dar clic en la parte superior derecha en el icono (Agregar) para agregar los resultados de las encuestas realizadas. 
          </p>

          <hr>

          <small class="text-success">AdmonGas siempre comprometidos con el medio ambiente. Utiliza hojas recicladas para imprimir tus encuestas. </small>

          <div class="text-center fs-12"><i class="ti ti-recycle text-success"></i></div>
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->