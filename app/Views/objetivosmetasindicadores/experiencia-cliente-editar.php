<div id="container" class="mb-3" data-fecha="<?= $fecha ?>" data-id="<?= $id ?>" x-data="{ ...actions(), ...experienciaClienteEditar() }">

<div class="row mt-3">
    <div class="col-xl-3 col-lg-3 col-md-12 col-sm-12">
        <input type="date" class="form-control" x-model="fecha"
        :class="{'is-invalid': errors.fecha}"
         @input="errors.fecha = false">
    </div>
</div>
    

    <div class="row mt-3">
        <!-- TABLA - CUESTIONARIO -->
        <div class="col-xl-7 col-lg-7 col-md-12 col-sm-12">

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

                <div class="text-end mt-3">
                    <button class="btn bg-primary-subtle text-primary"
                            :disabled="loading"
                            @click="guardar()">
                        Agregar encuesta
                    </button>
                </div>
        </div>

        <!-- TABLA - CUESTIONARIO -->
        <div class="col-xl-5 col-lg-5 col-md-12 col-sm-12">
        
        <div style="max-height: 500px; overflow-y: auto;">
         <template x-if="clientes.length > 0">
        <div class="m-2">
            <table class="table table-sm table-hover table-striped table-bordered">
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

        <div class="text-end mt-4">
            <button class="btn btn-success"
                    @click="finalizarEncuesta()">
                Finalizar encuesta
            </button>
        </div>
        
        </div>
    </div>

    <div class="modal fade" id="modalDetalle" tabindex="-1">
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

        <template x-for="item in detalle.preguntas" :key="item.num_pregunta">
            <div class="p-1">
                <div class="card p-3">
                    <div style="font-size:1.2em">
                        <span x-text="item.num_pregunta"></span>.-
                        <span x-text="item.pregunta"></span>
                    </div>

                    <div style="margin-top:5px; font-size:1.1em;"
                         :style="'color:' + getColor(item.resultado)">
                        R: <span x-text="getTexto(item.resultado)"></span>
                    </div>
                </div>
            </div>
        </template>

        <hr>

        <div>
            <small class="text-secondary">Comentario:</small>
            <p style="font-size:1.1em" x-text="detalle.comentario"></p>
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