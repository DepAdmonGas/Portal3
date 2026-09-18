<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>"
x-data="{ ...actions(), ...evaluacionRequisitos()}">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

<div class="row mt-3">

<div class="col-12 col-md-4  mb-4">
    <a href="/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/pdf" 
       class="card  text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body pb-4 p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <!-- Icono circular -->
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px;">
                    <i class="ti ti-file-text text-white display-6"></i> 
                </div>

                <!-- Título a la derecha -->
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end">
                    <h4 class="fw-bold text-dark mb-0 lh-sm">
                        Matriz de evaluación del cumplimiento legal
                    </h4>
                </div>
            </div>
        </div>

        <!-- Pie de la tarjeta -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
            <span class="small">Descargar documento</span>
            <div class="icon-transition">
                <i class="ti ti-download fs-5"></i>
            </div>
        </div>
    </a>
</div>

    <div class="col-12 col-md-8">

    <div class="card">
<div class="card-header py-3">
    <div class="d-flex align-items-center">
        <h5 class="card-title mb-0">Matriz de evaluación del cumplimiento legal</h5>
        <div class="ms-auto d-flex align-items-center">
            <div class="dropdown">
                <button type="button" class="btn bg-primary-subtle text-primary dropdown-toggle" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti ti-dots-vertical fs-4"></i>
                </button>
                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                    <li>
                        <a class="dropdown-item pointer" @click="openNuevo()"><i class="ti ti-plus"></i> Nuevo</a>
                    </li>
                    <li>
                        <a class="dropdown-item pointer" href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.022.docx"><i class="ti ti-download"></i> Descargar</a>
                    </li>
                </ul>
            </div>   
        </div>
    </div>   
</div>

    <div class="card-body p-0">
    
    <table class="table table-striped mb-0 table-bordered text-nowrap align-middle">
        <thead>
            <tr>
                <th class="text-center align-middle" width="48px">
                    #
                </th>

                <th class="text-center align-middle">
                    Fecha
                </th>

                <th class="text-center align-middle" width="48px">
                    <i class="ti ti-download text-primary fs-6"></i>
                </th>

                <th class="text-center  align-middle" width="48px">
                    <i class="ti ti-trash text-danger fs-6"></i>
                </th>
            </tr>
        </thead>
        <tbody>

        <template
                    x-for="registro in registros"
                    :key="registro.id">

                    <tr>

                        <td
                            class="text-center fw-bold"
                            x-text="registro.numero">
                        </td>

                        <td
                            class="text-center"
                            x-text="registro.fecha_larga">
                        </td>

                        <td class="text-center">

                            <a
                                :href="registro.url_pdf"
                                download>

                                <i class="ti ti-download fs-6 text-primary"></i>
                            </a>

                        </td>

                        <td class="text-center">

                        <a @click="eliminar(registro.id)"><i class="ti ti-trash fs-6 text-danger pointer"></i></a>

                        </td>

                    </tr>

        </template>

                <tr
                    x-show="!loading && registros.length === 0">

                    <td
                        colspan="4"
                        class="text-center">

                        <small>
                            No se encontró información para mostrar
                        </small>

                    </td>

                </tr>


        </tbody>
    </table>

    </div>
</div>

</div>
</div>

<!-- Modal Nuevo -->

<div class="modal fade"
     id="modalNuevo"
     tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h5 class="modal-title text-white">
                    <i class="ti ti-report-search"></i>
                    Informe de revisión de resultados
                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

               <label class="form-label mb-1">
                  * Fecha:
              </label>

              <input
                  type="date"
                  class="form-control"
                  x-model="fecha"
                  :class="errors.fecha ? 'is-invalid' : ''"
                  @input="errors.fecha = false">

                  <label class="form-label mt-3 mb-1">
                      * Documento:
                  </label>

                  <input
                      type="file"
                      class="form-control"
                      accept=".pdf"
                      :class="errors.documento ? 'is-invalid' : ''"
                      @change="
                          documento = $event.target.files[0];
                          errors.documento = false;
                      ">

            </div>

            <div class="modal-footer">

                <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cancelar

                </button>

                <button
                    class="btn btn-success"
                    @click="guardar()">

                    <i class="ti ti-check"></i> Guardar

                </button>

            </div>

        </div>

    </div>

</div>
<?php endif; ?>
</div>
