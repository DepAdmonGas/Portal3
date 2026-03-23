
<div class="text-end">
<button type="button" class="btn mb-1 bg-primary-subtle text-primary px-4 fs-4" data-bs-toggle="modal" data-bs-target="#nuevoGrupo">Nuevo Grupo</button>
</div>

<div class="datatables">

    <div class="table-responsive">
      <table id="table-grupos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>

          <tr>
            <th>#</th>
            <th>Nombre del Grupo</th>
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


<div class="modal fade"
     id="nuevoGrupo"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     x-data="grupoForm()"
     @editar-grupo.window="abrirEditar($event.detail)">

    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" x-text="modo === 'create' ? 'Crear Nuevo Grupo' : 'Editar Grupo'"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetForm()"></button>
            </div>

            <div class="modal-body">
                <label class="form-label">Nombre del Grupo</label>
                <textarea
                    class="form-control"
                    rows="3"
                    placeholder="Nombre del Grupo"
                    x-model="nombreGrupo"
                    @input="error = false"
                    :class="error ? 'border border-danger' : ''"
                ></textarea>

                <small class="text-danger" x-show="error">
                  El nombre del grupo es obligatorio
                </small>
            </div>

            <div class="modal-footer">
                <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        :disabled="enviando"
                        @click="resetForm()">
                    Cancelar
                </button>

                <button class="btn btn-success"
                @click="modo === 'create' ? guardarGrupo() : actualizarGrupo()"
                :disabled="enviando">
                  <span x-show="!enviando">Guardar</span>
                  <span x-show="enviando">Enviando...</span>
              </button>
            </div>
        </div>
    </div>
</div>
