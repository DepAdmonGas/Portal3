<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>"
x-data="{ ...actions(), ...evaluacionRequisitos()}">

<div class="row mt-3">
    <div class="col-12 col-md-4">

      <div class="card">
      <div class="card-body">
      <h5>Matriz de evaluación del cumplimiento legal</h5>

      <div class="text-end">
      <a class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info" type="button"
      href="/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/pdf"><i class="ti ti-download"></i> Descargar</a>
      </div>
      </div>
      </div>

    </div>
    <div class="col-12 col-md-8">

    <div class="card">
        <div class="card-header">
 <div class="d-flex align-items-center">
        <h5 class="card-title">Matriz de evaluación del cumplimiento legal</h5>
        <div class="ms-auto">
        <div class="dropdown dropstart">
                <a href="javascript:void(0)" class="btn btn-light" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots-vertical fs-6"></i>
                </a>
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
    <div class="card-body p-3">
    
    <table class="table table-striped pb-4 table-bordered  text-nowrap align-middle">
        <thead>
            <tr>
                <th class="text-center align-middle">
                    #
                </th>

                <th class="text-center align-middle">
                    Fecha
                </th>

                <th class="text-center  align-middle" width="40">
                    <i class="ti ti-download text-primary fs-6"></i>
                </th>

                <th class="text-center  align-middle" width="40">
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

               <label class="form-label">
                  * Fecha:
              </label>

              <input
                  type="date"
                  class="form-control"
                  x-model="fecha"
                  :class="errors.fecha ? 'is-invalid' : ''"
                  @input="errors.fecha = false">

                  <label class="form-label mt-2">
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

</div>