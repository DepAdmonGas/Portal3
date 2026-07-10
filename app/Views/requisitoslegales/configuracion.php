<div id="container" class="pb-4" x-data="actions()">

  <div class="text-end">
        <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevo">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
        ?>  
    </div>

  <div class="datatables mt-4">
    <div class="table-responsive">
      <table id="table-lista-requisitos-legales-configuracion" class="table table-bordered table-striped mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
           <th>Gobierno</th>
            <th>Dependencia</th>
            <th>Permiso</th>
            <th>Fundamento</th>
          <th class="text-center">
          <a class="text-muted"><i class="ti ti-trash fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
                    
</div>

<div class="modal fade"
     id="nuevo"
     x-ref="modalNuevo"
     x-data="{ ...actions(), ...requisitosLegalesForm()}"
     x-init="getDependencias()"
     tabindex="-1">

    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">Agregar requisito legal</h4>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <!-- Politica -->
                <label class="form-label">* Gobierno:</label>
                <select class="form-control"
                        x-model="gobierno"
                        @change="errors.gobierno = false"
                        :class="errors.gobierno ? 'is-invalid' : ''">
                  <option value=""></option>
                  <option value="Municipal">Municipal</option>
                  <option value="Estatal">Estatal</option>
                  <option value="Federal">Federal</option>
                  <option value="Varios">Varios</option>
                </select>

                <!-- Dependencia -->
                <label class="form-label mt-3">* Dependencia:</label>
                <div class="select2-modal-field is-select2-pending"
                     x-ref="dependenciaWrapper"
                     :class="errors.dependencia ? 'is-invalid' : ''">
                    <select id="selectDependencia"
                            x-ref="selectDependencia"
                            data-width="100%">
                
                    <option value="">Seleccione</option>

                    <template x-for="dep in dependencias" :key="dep.id">
                        <option :value="dep.dependencia" x-text="dep.dependencia"></option>
                    </template>
                </select>
                </div>

                <!-- Permiso -->
                <label class="form-label mt-3">* Permiso:</label>
                <textarea class="form-control"
                          rows="6"
                          x-model="permiso"
                          @input="errors.permiso = false"
                          :class="errors.permiso ? 'is-invalid' : ''"></textarea>

                <!-- Fundamento -->
                <label class="form-label mt-3">* Fundamento:</label>
                <textarea class="form-control"
                          rows="6"
                          x-model="fundamento"
                          @input="errors.fundamento = false"
                          :class="errors.fundamento ? 'is-invalid' : ''"></textarea>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer">

                <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                    <i class="ti ti-x"></i> Cancelar
                </button>

                <button type="button"
                        class="btn btn-success"
                        @click="submit()"
                        :disabled="loading">

                        <i class="ti ti-check"></i>

                    <span x-show="!loading">Guardar</span>
                    <span x-show="loading">Guardando...</span>

                </button>

            </div>

        </div>
    </div>
</div>
