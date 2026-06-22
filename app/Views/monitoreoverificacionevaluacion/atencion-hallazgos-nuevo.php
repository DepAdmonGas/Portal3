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

<div class="bg-white mt-3 p-3">

        <div class="row">

            <div class="col-md-4">

                <label class="form-label">
                    Fecha de la auditoría
                </label>

                <input
                    type="date"
                    class="form-control"
                    x-model="fecha_auditoria"
                    @change="guardarEncabezado()">

            </div>

            <div class="col-md-4">

                <label class="form-label">
                    No. de control de la auditoría
                </label>

                <input
                    type="text"
                    class="form-control"
                    x-model="no_control"
                    @blur="guardarEncabezado()">

            </div>

            <div class="col-md-4">

                <label class="form-label">
                    Tipo de auditoría
                </label>

                <input
                    type="text"
                    class="form-control"
                    x-model="tipo_auditoria"
                    @blur="guardarEncabezado()">

            </div>

        </div>

        <hr>

        <div class="d-flex justify-content-between align-items-center mt-4">

            <h5 class="mb-0">
                Hallazgos
            </h5>

            <button
                class="btn btn-primary"
                @click="abrirModal()">
                <i class="ti ti-plus"></i>
                Nuevo
            </button>

        </div>

        <div class="mt-3">

            <table class="table table-bordered table-striped table-sm mt-3">
            <thead>
            <tr>
            <th class="align-middle">SASISOPA</th>
            <th class="align-middle">Hallazgos</th>
            <th class="align-middle">Acción preventiva por hallazgo</th>  
            <th class="align-middle">Fecha de implementación</th>
            <th class="align-middle">Evidencia</th>
            <th class="align-middle">% de cumplimiento</th>
            <th class="align-middle text-center" width="36"><i class="ti ti-paperclip fs-7 text-primary"></i></th>
            <th class="align-middle text-center" width="36"><i class="ti ti-edit fs-7 text-info"></i></th>
            <th class="align-middle text-center" width="36"><i class="ti ti-trash fs-7 text-danger"></i></th>
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

    <td class="align-middle text-center">
        <a @click="abrirModalEvidencia(registro.id)">
            <i class="ti ti-paperclip fs-7 text-primary"></i>
        </a>
    </td>

    <td class="align-middle text-center">
        <a @click="editar(registro)">
            <i class="ti ti-edit fs-7 text-info"></i>
        </a>
    </td>

    <td class="align-middle text-center">
        <a @click="eliminar(registro.id)">
            <i class="ti ti-trash fs-7 text-danger"></i>
        </a>
    </td>

</tr>

        </template>

                <tr
                    x-show="!loading && registros.length === 0">

                    <td
                        colspan="9"
                        class="text-center">

                        <small>
                            No se encontró información para mostrar
                        </small>

                    </td>

                </tr>
            </tbody>
            </table>

        </div>

        <div class="text-end mt-3">

            <button onclick="window.history.back();"
                class="btn btn-success">
                Finalizar
            </button>

        </div>

</div>

<!-- Modal -->

<div class="modal fade"
     id="modalHallazgo"
     tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    <span x-show="modoHallazgo == 'create'">
                        Agregar Hallazgos
                    </span>

                    <span x-show="modoHallazgo == 'edit'">
                        Editar Hallazgos
                    </span>

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

               <label class="form-label">
                  * SASISOPA
              </label>

            <select class="form-control rounded-0"
                x-model.number="hallazgo.id_sasisopa"
                :class="errors.id_sasisopa ? 'is-invalid' : ''"
                @input="errors.id_sasisopa = false">

            <option value="">Seleccione</option>

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

                    Cancelar

                </button>

                <button
                    class="btn btn-success"
                    @click="guardar()">

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

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Agregar evidencia
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="input-group mb-3">

                    <input
                        type="file"
                        class="form-control"
                        @change="seleccionarArchivo($event)">

                    <button
                        class="btn btn-primary"
                        @click="subirEvidencia()"
                        :disabled="loadingEvidencia">

                        Subir

                    </button>

                </div>

                <table class="table table-bordered table-striped table-sm">

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
                                        class="btn btn-sm btn-danger"
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

        </div>

    </div>

</div>

</div>