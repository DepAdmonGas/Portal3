<div id="container" class="pb-4"
x-data="{ ...actions(), ...capacitacionExterna() }">

<table class="table table-bordered table-sm mt-3">
    <tr>
    <td class="text-center align-middle"><img class="text-center" src="<?= $_ENV['APP_URL'] . '/assets/images/logos/Logo.png' ?>" style="width: 200px;"></td>
    <td colspan="2" class="text-center align-middle"><b>Programa de Capacitacion y adiestramiento </b></td>
    <td class="text-center align-middle">Fo.ADMONGAS.009</td>
    </tr>
    <tr>
    <td class="text-center align-middle">Realizado por: Nelly Estrada Garcia </td>
    <td class="text-center align-middle">Revisado por: Eduardo Galicia Flores </td>
    <td class="text-center align-middle">Autorizado por: Tomas Tarno Quinzaños </td>
    <td class="text-center align-middle">Fecha de autorizacion 01/10/2018</td>
    </tr>
</table>

    <div class="d-flex align-items-center">
         <div class="ms-auto">
            <?= 
              !empty($permisos['crear']) ? 
              '<button type="button" class="btn bg-primary-subtle text-primary" href="javascript:void(0)" @click="openModalNuevo()">
              <i class="ti ti-plus"></i> Nuevo
              </button>' 
              : '' 
            ?>   
          </div>
      </div>

    <div class="datatables">
    <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
    <table class="table table-bordered table-striped" id="table-capacitacion-externa">
    <thead>	
    <tr class="bg-primary text-white">
    <th class="text-center align-middle">#</th>
    <th class="text-center align-middle">Curso</th>
    <th class="text-center align-middle">Fecha programada</th>
    <th class="text-center align-middle">Duración</th>
    <th class="text-center align-middle">Instructor</th>
    <th class="text-center align-middle">Fecha real</th>
    <th class="text-center align-middle">
    <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
    </th>
    </tr>
    </thead>
    <tbody></tbody>
    </table>
    </div>
    </div>

    <!-- MODAL AGREGAR -->
    <div class="modal fade" id="modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header modal-colored-header bg-primary text-white">
        <h4 class="modal-title text-white">
        <i class="ti ti-progress-check ms-2"></i>    
        CAPACITACIÓN EXTERNA</h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="closeModal()"></button>
    </div>

    <div class="modal-body">


        <label class="form-label">* Nombre del curso:</label>
        <textarea class="form-control mb-2" x-model="curso"
                :class="errors.curso ? 'is-invalid' : ''"
                @input="errors.curso = false"></textarea>

        <label class="form-label">* Fecha programada:</label>
        <input type="date" class="form-control mb-2" x-model="fecha_programada"
                :class="errors.fecha_programada ? 'is-invalid' : ''"
                @input="errors.fecha_programada = false">

        <label class="form-label">Duración:</label>
        <div class="row mb-2">
            <div class="col-8">
                <input type="text" class="form-control" x-model="duracion">
            </div>
            <div class="col-4">
                <select class="form-control" x-model="duraciondetalle">
                    <option value="">Selecciona una opción...</option>
                    <option value="Minutos">Minutos</option>
                    <option value="Horas">Horas</option>
                </select>
            </div>
        </div>

       <label class="form-label">Nombre del instructor:</label>
        <input type="text" class="form-control" x-model="instructor">

        <div x-show="mode === 'edit'">

            <div class="mt-3 mb-2 text-primary">
                <small>* Agrega la fecha real de cuando se impartió el curso</small>
            </div>

            <label class="form-label">Fecha real:</label>
            <input type="date" class="form-control" x-model="fecha_real">

        </div>

    </div>

    <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" @click="closeModal()"><i class="ti ti-x"></i> Cancelar</button>
        <button class="btn btn-success" @click="guardar()">
            <i class="ti ti-check"></i>
            <span x-text="mode === 'edit' ? 'Actualizar' : 'Guardar'"></span>
        </button>
    </div>

    </div>
    </div>
    </div>

    <!-- ------ -------->

    <div class="modal fade" id="modal-personal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header head-modal card-header bg-primary">
                <h4 class="modal-title text-white">
                    <i class="ti ti-users ms-2"> </i> TRABAJADORES
                </h4>
                <button type="button" class="btn-close btn-close-white" @click="closeModalPersonal()"></button>
            </div>

            <div class="modal-body">

                <label class="form-label">Nombre del trabajador:</label>

                <select class="form-control mb-2" x-model="selectedEmpleado">
                    <option value="">Selecciona una opción...</option>
                    <template x-for="user in usuarios" :key="user.id">
                        <option :value="user.id" x-text="user.nombre"></option>
                    </template>
                </select>

                <div class="text-end">
                <button class="btn btn-success mb-3"
                    @click="addEmpleado()">
                    Agregar 
                </button>
                </div>

                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th class="text-center"><a class="text-muted"><i class="ti ti-trash fs-6"></i></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in personal" :key="item.id">
                            <tr>
                                <td x-text="index + 1"></td>
                                <td x-text="item.nombre"></td>
                                <td class="text-center">
                                    <a class="text-muted" href="javascript:void(0)" 
                                    @click="removeEmpleado(item.id)"><i class="ti ti-trash fs-6 text-danger"></i></a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>

</div>