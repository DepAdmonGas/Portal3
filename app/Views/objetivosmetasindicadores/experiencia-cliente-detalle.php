<div id="container" class="mb-3" data-id="<?= $id ?>" x-data="experienciaClienteDetalle">

<div class="mt-3"><h4>Reporte <?= $fecha ?></h4></div>

<div class="row mt-3">
        <!-- TABLA - CUESTIONARIO -->
        <div class="col-xl-7 col-lg-7 col-md-12 col-sm-12">


        <div id="chart"></div>
        
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

        
        </div>
    </div>


 <div class="row">

        <template x-for="p in preguntas" :key="p.id">
            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">

                <div class="bg-white mt-4 p-2">

                    <div class="p-2 fw-bold">
                        <span class="fw-bold fs-5" x-text="p.num_pregunta"></span>.-
                        <span class="fw-bold fs-5" x-text="p.pregunta"></span>
                    </div>

                    <div :id="'chartPregunta' + p.id" style="height:250px;"></div>

                </div>

            </div>
        </template>

    </div>

<div class="modal fade" id="modalDetalle" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <!-- HEADER -->
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white" x-text="detalle.nombre"></h5>
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