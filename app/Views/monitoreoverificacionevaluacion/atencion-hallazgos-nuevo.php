<div id="container"
x-data="{ ...actions(), ...atencionHallazgos()}"
 data-id="<?= $hallazgos->id ?>"
 x-init="
    id='<?= $hallazgos->id ?>';
    fecha_auditoria='<?= $hallazgos->fecha ?>';
    no_control='<?= htmlspecialchars($hallazgos->no_control ?? '', ENT_QUOTES) ?>';
    tipo_auditoria='<?= htmlspecialchars($hallazgos->tipo_auditoria ?? '', ENT_QUOTES) ?>';

     buscarHallazgos();
">
        <div class="text-end mt-3 mb-3">

            <button onclick="window.history.back();"
                class="btn btn-success">
                <i class="ti ti-check"></i>
                Finalizar
            </button>

        </div>

        <div class="card">
            <div class="card-body">
<div class="bg-white mt-3 p-3">

        <div class="row">

            <div class="col-md-4">

                <label class="form-label">
                    Fecha de la auditoría:
                </label>

                <input
                    type="date"
                    class="form-control"
                    x-model="fecha_auditoria"
                    @change="guardarEncabezado()">

            </div>

            <div class="col-md-4">

                <label class="form-label">
                    No. de control de la auditoría:
                </label>

                <input
                    type="text"
                    class="form-control"
                    x-model="no_control"
                    @blur="guardarEncabezado()">

            </div>

            <div class="col-md-4">

                <label class="form-label">
                    Tipo de auditoría:
                </label>

                <input
                    type="text"
                    class="form-control"
                    x-model="tipo_auditoria"
                    @blur="guardarEncabezado()">

            </div>

        </div>
        

</div>
</div>
</div>


<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center mt-0">

            <h5 class="mb-0 form-label">
                Hallazgos
            </h5>

            <button
                class="btn bg-primary-subtle text-primary"
                @click="abrirModal()">
                <i class="ti ti-plus"></i>
                Nuevo
            </button>

        </div>
    </div>

    <div class="card-body">
        <div class="mt-0">
<div class="table-responsive">


            <table class="table table-striped pb-4 table-bordered   align-middle">
            <thead>
            <tr>
            <th class="align-middle">SASISOPA</th>
            <th class="align-middle">Hallazgos</th>
            <th class="align-middle">Acción preventiva por hallazgo</th>  
            <th class="align-middle">Fecha de implementación</th>
            <th class="align-middle">Evidencia</th>
            <th class="align-middle">% de cumplimiento</th>
            <th class="alling-middle text-center whidth=36"><i class="ti ti-dots-vertical fs-6"></i></th>
            </tr>
            </thead>
            <tbody>
        <template
            x-for="registro in registros"
            :key="registro.id">

            <tr>

    <td class="align-middle" x-text="registro.sasisopa"></td>
    <td class="align-middle" x-text="registro.hallazgos"></td>
    <td class="align-middle" x-text="registro.accion"></td>
    <td class="align-middle" x-text="registro.fecha_larga"></td>
    <td class="align-middle text-center">
        <template
            x-for="evidencia in registro.evidencias"
            :key="evidencia.id">
            <div>
                <a
                    :href="evidencia.url"
                    target="_blank"
                    x-text="evidencia.archivo">
                </a>
            </div>

        </template>

    </td>

    <td
        class="align-middle text-center fw-bolder"
        x-text="registro.cumplimiento">
    </td>



    <td class="text-center">

<div class="dropdown dropstart">
        <a href="javascript:void(0)"data-bs-toggle="dropdown">
        <i class="ti ti-dots-vertical fs-6"></i>
    </a>


    <ul class="dropdown-menu">

                <li>
                          <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            href="javascript:void(0)" @click="abrirModalEvidencia(registro.id)">
                          <i class="ti ti-camera-plus"></i>Evidencia
                        </a>
                </li>
                <li>
                    <a class="dropdown-item pointer d-flex align-items-center gap-3"
                    href="javascrip:void(0)" @click="editar(registro)">
            <i class="ti ti-edit"></i>Editar
        </a> 
        </li>
        <li>
            <a class="dropdown-item pointer d-flex align-items-center gap-3"
                href="javascript:void(0)" @click="eliminar(registro.id)">
                <i class="ti ti-trash"></i> Eliminar
            </a>
        </li>

    </ul>



    </td>

</tr>

        </template>

                <tr
                    x-show="!loading && registros.length === 0">

                    <td
                        colspan="9"
                        class="text-center">

                        <small>
                            No se encontró información 
                        </small>

                    </td>

                </tr>
            </tbody>
            </table>

        </div>
        </div>
    </div>
</div>




<!-- Modal -->

<div class="modal fade"
     id="modalHallazgo"
     tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h5 class="modal-title text-white">

                    <span x-show="modoHallazgo == 'create'">
                        <i class="ti ti-clipboard-plus"></i>
                        Nuevo Hallazgo
                    </span>

                    <span x-show="modoHallazgo == 'edit'">
                        <i class="ti ti-clipboard-search"></i>
                        Editar Hallazgos
                    </span>

                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

               <label class="form-label">
                  * SASISOPA:
              </label>

            <select class="form-select rounded-0"
                x-model.number="hallazgo.id_sasisopa"
                :class="errors.id_sasisopa ? 'is-invalid' : ''"
                @input="errors.id_sasisopa = false">

            <option value="">Seleccione una opcion...</option>

            <template x-for="item in sasisopaOptions" :key="item.id">
                <option
                    :value="item.id"
                    x-text="item.nombre"
                ></option>
            </template>

        </select>

                  <label class="form-label mt-2">
                      * Hallazgos:
                  </label>

                  <textarea class="form-control"
                  x-model="hallazgo.hallazgo"
                  :class="errors.hallazgo ? 'is-invalid' : ''"
                  @input="errors.hallazgo = false"></textarea>

                   <label class="form-label mt-2">
                      * Acción preventiva por hallazgo:
                  </label>

                  <textarea class="form-control"
                  x-model="hallazgo.accion"
                  :class="errors.accion ? 'is-invalid' : ''"
                  @input="errors.accion = false"></textarea>

                   <label class="form-label mt-2">
                      * Fecha de implementación:
                  </label>

                  <input type="date" class="form-control"
                  x-model="hallazgo.fecha"
                  :class="errors.fecha ? 'is-invalid' : ''"
                  @input="errors.fecha = false"
                  >

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

                    <i class="ti ti-check"></i>

                    <span x-show="modoHallazgo == 'create'">
                        Guardar
                    </span>

                    <span x-show="modoHallazgo == 'edit'">
                        Actualizar
                    </span>

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal Anexo -->

<div class="modal fade"
     id="modalEvidencia"
     tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    <i class="ti ti-camera-plus"></i>
                     Evidencia
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body pb-0">

                <div class="input-group mb-3">

                    <input
                        type="file"
                        class="form-control"
                        @change="seleccionarArchivo($event)">

                    <button
                        class="btn btn-success"
                        @click="subirEvidencia()"
                        :disabled="loadingEvidencia">
<i class="ti ti-check"></i>
                        Guardar

                    </button>

                </div>

                <table class="table table-bordered table-striped">

                    <tbody>

                        <template
                            x-for="item in evidencias"
                            :key="item.id">

                            <tr>

                                <td class="align-middle">
                                    <a
                                        :href="item.url"
                                        target="_blank"
                                        x-text="item.archivo">
                                    </a>

                                </td>

                                <td
                                    width="50"
                                    class="text-center align-middle">

                                    <button
                                        class="btn  btn-danger"
                                        @click="eliminarEvidencia(item.id)">

                                        <i class="ti ti-trash"></i>

                                    </button>

                                </td>

                            </tr>

                        </template>

                        <tr
                            x-show="!loadingEvidencia && evidencias.length === 0">

                            <td colspan="2" class="text-center">

                                No se encontraron evidencias

                            </td>

                        </tr>

                    </tbody>

                </table>
                

            </div>
            <div class="modal-footer">
  <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cancelar

                </button>
            </div>

        </div>

    </div>

</div>

</div>