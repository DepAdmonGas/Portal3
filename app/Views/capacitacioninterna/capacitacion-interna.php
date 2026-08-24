<div id="container" class="pb-4"
x-data="{ ...actions(), ...capacitacionInterna() }" data-idtema="<?= $idTema ?? '' ?>" data-idmodulo="<?= $idModulo ?? '' ?>">

<div class="row mt-3">
<div class="col-9 p-2"><h5><?= $nom_tema ?? '' ?></h5></div>
<div class="col-md-3 col-xl-3">
    <select class="form-select mt-2"  @change="irATema($event)">
        <option value="">Selecciona un tema</option>
            <optgroup label="<?= $nom_modulo ?? 'Temas' ?>">
            <?php foreach($temas ?? [] as $tema): ?>
            <option value="<?= $tema->id ?>"
            data-modulo="<?= $idModulo ?? 'Modulo' ?>">
            <?= $tema->num_tema ?> - <?= $tema->titulo ?>
            </option>
            <?php endforeach; ?>
            </optgroup>
    </select>
</div>

    <div class="datatables">
        <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
      <table id="table-capacitacion-interna" class="table table-bordered table-striped mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
            <th>Nombre Usuario</th>
            <th>Puesto</th>
            <th>Telefono</th>
            <th>Email</th>
            <th>Fecha Programada</th>
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

  <div class="modal fade" id="modalProgramar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">
                    <i class="ti ti-chalkboard-teacher"></i>
                CAPACITACIÓN INTERNA</h4>
                <button type="button" class="btn-close btn-close-white"
                        @click="closeModal('programar')"></button>
            </div>

            <div class="modal-body">

                <h5 class="text-start" x-text="programacion.titulo"></h5>

                <label class="form-label mt-3">* Fecha programada:</label>
                <input type="date"
                       class="form-control"
                       x-model="programacion.fecha"
                       :class="errorsProgramacion.fecha ? 'is-invalid' : ''">

            </div>

            <div class="modal-footer">
                <button class="btn bg-danger-subtle text-danger"
                        @click="closeModal('programar')"><i class="ti ti-x"></i> Cancelar</button>

                <button class="btn btn-success"
                        @click="guardarProgramacion()"><i class="ti ti-check"></i> Guardar</button>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalCursos" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">
                <i class="ti ti-chalkboard-teacher"></i>    
                CAPACITACIÓN INTERNA</h4>
                <button type="button" class="btn-close btn-close-white"
                        @click="closeModal('cursos')"></button>
            </div>

            <div class="modal-body">

                <h5 class="text-start" x-text="cursosTitulo"></h5>

                <h6 class="text-start text-muted mt-2" x-text="nombreUsuarioCursos"></h6>
           
                    <div class="table-responsive table-striped overflow-x-auto overflow-y-hidden">
                    <table class="table table-bordered table-sm">
                        <thead class="text-center">
                            <tr>
                                <th>Fecha Programada</th>
                                <th>Fecha Real</th>
                                <th>Resultado</th>
                                <th>Observaciones</th>
                                <th><i class="ti ti-download fs-6"></i></th>
                                <th><i class="ti ti-trash fs-6"></i></th>
                            </tr>
                        </thead>

                        <tbody>
                            <template x-for="c in cursos" :key="c.id">
                                <tr class="text-center align-middle">
                                    <td x-text="formatearFecha(c.fecha_programada)"></td>
                                    <td x-text="formatearFecha(c.fecha_real)"></td>

                                    <td>
                                        <span :class="c.color" x-text="c.texto"></span>
                                    </td>

                                    <td x-text="c.observaciones || 'S/I'"></td>

                                    <td>
                                      <template x-if="c.estado == 1">
                                          <a style="cursor:pointer" :href="`/cursos/descargar/${c.id}`" target="_blank">
                                              <i class="ti ti-download fs-6"></i>
                                          </a>
                                      </template>

                                      <template x-if="c.estado == 0">
                                          <a>
                                              <i class="ti ti-x fs-6"></i>
                                          </a>
                                      </template>
                                  </td>

                                    <td>
                                        <a @click="eliminarCurso(c.id)"
                                           class="text-danger"
                                           style="cursor:pointer">
                                            <i class="ti ti-trash fs-6"></i>
                                        </a>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="cursos.length === 0">
                                <td colspan="5" class="text-center text-muted">
                                    No se encontro información
                                </td>
                            </tr>
                        </tbody>

                    </table>
                </div>

            </div>

        </div>
    </div>
</div>

</div>