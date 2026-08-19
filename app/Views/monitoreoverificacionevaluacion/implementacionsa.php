<div id="container"
x-data="{ ...actions(), ...implementacionSA()}">

<div class="text-end">
      <?= 
        !empty($permisos['crear']) ? 
        '<button type="button" class="btn bg-primary-subtle text-primary" @click="openModalNuevo()">
        <i class="ti ti-plus"></i> Nuevo
        </button>' 
        : '' 
        ?>     
    </div>

      <div class="datatables mt-3">
    <div class="table-responsive overflow-x-auto overflow-hidden">
      <table id="table-implementacionsa" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
            <th>Responsable</th>
            <th>Fecha</th>
            <th>Preguntas</th>
            <th>SI</th>
            <th>NO</th>
            <th>Resultado</th>
          <th class="text-center">
          <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

    <!-- Modal nuevo -->
    <div class="modal fade"
         id="modalImplementacion"
         tabindex="-1">

        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white">
                        <i class="ti"
   :class="mode === 'create' ? 'ti-plus' : 'ti-edit'">
</i>
                        <span x-text="mode === 'create'
                        ? 'Nueva Implementación del SA'
                        : mode === 'edit'
                        ? 'Editar Implementación del SA'
                        : 'Detalle Implementación del SA'"></span>
                    </h4>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">

                <div class="text-center"><b class="fs-6">Lee detalladamente y contesta de manera honesta los siguientes cuestionamientos</b></div>

                <label class="form-label mt-4">* Fecha</label>
                <input type="date" class="form-control w-50" x-model="fecha"
                :class="errors.fecha ? 'is-invalid' : ''"
                  @input="errors.fecha = false">

                

                <div class="row mt-3">

                    <template x-for="(grupo,index) in preguntas" :key="index">

                        <div class="col-md-6 mb-3">

                            <div class="card h-100">

                                <div class="card-body">

                                    <h5 class="mb-3">
                                        <span class="text-success"
                                            x-text="(index + 1) + '.'"></span>

                                        <span x-text="grupo.titulo"></span>
                                    </h5>

                                    <template
                                        x-for="pregunta in grupo.preguntas"
                                        :key="pregunta.id">

                                        <div class="mb-3">

                                            <p class="fw-bold"
                                            x-text="pregunta.id + '. ' + pregunta.texto">
                                            </p>

                                            <div>

                                                <label class="me-3">

                                                    <input type="radio"
                                                        :name="'pregunta_' + pregunta.id"
                                                        value="1"
                                                        x-model="pregunta.respuesta">

                                                    Sí

                                                </label>

                                                <label>

                                                    <input type="radio"
                                                        :name="'pregunta_' + pregunta.id"
                                                        value="0"
                                                        x-model="pregunta.respuesta">

                                                    No

                                                </label>

                                            </div>

                                        </div>

                                    </template>

                                </div>

                            </div>

                        </div>

                    </template>

                </div>

                </div>

                <div class="modal-footer">

                    <button class="btn bg-danger-subtle text-danger"
                            data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>

                    <button x-show="mode !== 'view'"
                            class="btn btn-success"
                            @click="guardar()">

                        <i class="ti ti-check"></i> Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- Modal detalle -->
    <div class="modal fade"
     id="modalDetalleImplementacion"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    <i class="ti ti-file-description"></i>
                    Detalle Implementación del SA
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-4">

                    <label class="fw-bolder">
                        Fecha
                    </label>

                    <div x-text="detalleFecha"></div>

                </div>

                <template
                    x-for="pregunta in detallePreguntas"
                    :key="pregunta.pregunta">

                    <div class="card mb-2">

                        <div class="card-body">

                            <div class="fw-bolder"
                                 x-text="pregunta.pregunta">
                            </div>

                            <div class="mt-2">

                                <span
                                    class="badge bg-info"
                                    x-show="pregunta.resultado == 1">

                                    Sí

                                </span>

                                <span
                                    class="badge bg-danger"
                                    x-show="pregunta.resultado == 0">

                                    No

                                </span>

                            </div>

                        </div>

                    </div>

                </template>

            </div>

            <div class="modal-footer">

                <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cerrar

                </button>

            </div>

        </div>

    </div>

    </div>

</div>
    