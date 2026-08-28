<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>"
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

      <div class="datatables ">
    <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
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
   :class="mode === 'create' ? 'ti-presentation-analytics' : 'ti-edit'">
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

                <div class="text-start"> <h5 class="form-label">Lee detalladamente y contesta de manera honesta los siguientes cuestionamientos </h5></div>

                <label class="form-label mt-2">* Fecha:</label>
                <input type="date" class="form-control w-50" x-model="fecha"
                :class="errors.fecha ? 'is-invalid' : ''"
                  @input="errors.fecha = false">

                

                <div class="row mt-3">

<!-- VISTA DE AGREGAR -->
                    <template x-for="(grupo,index) in preguntas" :key="index">

                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                            <div class="card">

                                
<div class="card-header bg-primary">
                                    <h5 class="mb-0 text-white">
                                        <span class="text-white"
                                            x-text="(index + 1) + '.'"></span>

                                        <span x-text="grupo.titulo"></span>
                                    </h5>
</div>
<div class="card-body p-0">
<div class="table-responsive">

    <table class="table table-striped table-bordered mb-0 p-0 align-middle">
        <thead>
<tr>
    <th>Pregunta:</th>
    <th class="text-center">Si</th>
    <th class="text-center">No</th>
</tr>

        </thead>
        <tbody>

                                    <template
                                        x-for="pregunta in grupo.preguntas"
                                        :key="pregunta.id">
<tr>
                                        <div class="">

                                            <td class="form-label"
                                            x-text="pregunta.id + '. ' + pregunta.texto">
                                            </td>

                                            <div>
<td class="text-center">


                                                    <input 
                                                    class="pointer"
                                                    type="radio"
                                                        :name="'pregunta_' + pregunta.id"
                                                        value="1"
                                                        x-model="pregunta.respuesta">

                                                    

</td>
<td class="text-center">
  

                                                    <input 
                                                    class="pointer"
                                                    type="radio"
                                                        :name="'pregunta_' + pregunta.id"
                                                        value="0"
                                                        x-model="pregunta.respuesta">

</td>
                                            </div>

                                         </div>
</tr>
                                       </template>

        </tbody>

    
                                  
                                    </table>
                                
                                </div>
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
                    <i class="ti ti-eye"></i>
                    Detalle Implementación del SA
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-4">

                    <label class="form-label">
                        Fecha
                    </label>

                    <div x-text="detalleFecha"></div>

                </div>

                
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
    <thead>
        <tr>
           <th>Pregunta</th> 
           <th>Respuesta</th>
        </tr>
    </thead>
    <tbody>
            
    <template
                    x-for="pregunta in detallePreguntas"
                    :key="pregunta.pregunta">
<tr>
                         <td class="form-label"
                                 x-text="pregunta.pregunta">
</td>

<td class="text-center">
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
                                </td>
</tr>
</template>

                        </div>

                    </div>

                
                </tbody>
</table>
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
    