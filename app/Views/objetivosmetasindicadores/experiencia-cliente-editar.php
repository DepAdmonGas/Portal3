<div id="container" class="mb-3" data-fecha="<?= $fecha ?>" data-id="<?= $id ?>" x-data="{ ...actions(), ...experienciaClienteEditar() }"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<div class="row g-0 ms-auto w-100 justify-content-between mt-3">
    <div class="col-12 col-md-auto d-grid mb-3 mb-md-0">
        <div class="items-d-flex aling-start">
        <input type="date" class="form-control" x-model="fecha"
        :class="{'is-invalid': errors.fecha}"
         @input="errors.fecha = false">
         </div>
    </div>

    
<div class="col-12 col-md-auto d-grid">

            <button class="btn btn-success"
                    @click="finalizarEncuesta()">
                    <i class="ti ti-check"></i>
                Finalizar 
            </button>
     
        </div>
</div>


    <div class="row mt-3">
        <!-- TABLA - CUESTIONARIO -->
        <div class="col-xl-7 col-lg-7 col-md-12 col-sm-12">
<div class="card">
<div class="card-body">
        <label class="form-label">Nombre:</label>
        <input type="text" class="form-control" x-model="nombre"
        :class="{'is-invalid': errors.nombre}"
        @input="errors.nombre = false">

                <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th>Pregunta</th>
                        <th>Respuesta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cuestionario as $index => $item): ?>
                        <tr>
                            <td class="text-center"><?= $item->num_pregunta ?></td>
                            <td><?= $item->pregunta ?></td>
                            <td class="p-0 align-middle">
                                <select class="form-control border-0"
                                    data-pregunta="<?= $item->id ?>"
                                    x-model="preguntas[<?= $index ?>].respuesta"
                                    :class="{'is-invalid': errors.preguntas.includes(<?= $index ?>)}"
                                    @change="errors.preguntas = errors.preguntas.filter(i => i !== <?= $index ?>)">
                                <option value="0">SELECCIONA UNA OPCIÓN...</option>
                                    <option value="4">Excelente</option>
                                    <option value="3">Bueno</option>
                                    <option value="2">Regular</option>
                                    <option value="1">Malo</option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>


                <label class="form-label">Comentario:</label>
                <textarea class="form-control" x-model="comentario"></textarea>
                </div>
<div class="card-footer">
                <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid">
    <button class="btn bg-primary-subtle text-primary"
                            :disabled="loading"
                            @click="guardar()">
                            <i class="ti ti-plus"></i>
                         Nuevo
                    </button>
                    </div>
                
                </div>

</div>

                <!-----card termina abajo----->
        </div>



</div>
        <!-- TABLA - CUESTIONARIO -->
        <div class="col-xl-5 col-lg-5 col-md-12 col-sm-12">
        
        
        <!----aqui empieza la card de clientes---->
<div class="card">

<div class="card-header card-colored-header bg-primary">

            <h4 class="card-title text-white mb-0">
                <i class="ti ti-user"></i>
                Encuesta de clientes</h4>
        
    </div>
 <div class="card-body p-0">
        <div style="max-height: 500px; overflow-y: auto;">
         <template x-if="clientes.length > 0">
        <div class="m-0">
            <table class="table  table-striped table-bordered mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(cliente, index) in clientes" :key="cliente.id">
                        <tr style="cursor:pointer"
                            @click="Detalle(cliente.id)">
                            <td x-text="index + 1"></td>
                            <td x-text="cliente.nombre"></td>
                        </tr>
                    </template>

                    <tr>
                        <td colspan="2">
                            Total: <b x-text="clientes.length"></b> clientes encuestados
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </template>

    <template x-if="clientes.length === 0">
        <div class="text-center p-4">
            <small class="text-secondary">No se encontró información</small>
        </div>
    </template>

    </div>



</div>
    <!----------------aqui debe terminar la card---->
</div>


        </div>
    </div>

    <div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <!-- HEADER -->
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white">
           <i class="ti ti-user"></i>
            <label x-text="detalle.nombre"></label>
            </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <!-- BODY -->
  <div class="modal-body">

    <div class="table-responsive pb-4">
        <table class="table table-striped table-bordered mb-0 align-middle">
            <thead>
                <tr>
                    <th>Pregunta</th>
                    <th class="text-center">Respuesta</th>
                </tr>
            </thead>

            <tbody>
                <template x-for="item in detalle.preguntas" :key="item.num_pregunta">
                    <tr>
                        <td class="py-3">
                            <span x-text="item.num_pregunta"></span>.-
                            <span x-text="item.pregunta"></span>
                        </td>

                        <td class="text-center py-3">
                            <span
                                class="badge rounded-pill"
                                :style="'background-color:' + getColor(item.resultado)"
                                x-text="getTexto(item.resultado)">
                            </span>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div>
        <div class="form-label">Comentario:</div>
        <p x-text="detalle.comentario"></p>
    </div>

</div>


  <div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
    <div class="col-12 col-md-auto d-grid">
      <button type="button"
class="btn bg-danger-subtle text-danger"
data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cancelar
</button>
    </div>
</div>
      
        </div>
        

      </div>

    </div>
  </div>
</div>

</div>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Agregar experiencia del cliente
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
           En esta sección deberás vaciar la información de cada una de las encuestas. Recuerda que para obtener datos estadísticamente verídicos por ningún motivo se deberá falsificar la información.  </b>
          </p>
         
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->