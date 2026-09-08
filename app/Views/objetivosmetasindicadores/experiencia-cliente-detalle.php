<div id="container" class="mb-3" data-id="<?= $id ?>" x-data="experienciaClienteDetalle"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">



<div class="row mt-3">
        <!-- TABLA - CUESTIONARIO -->
        <div class="col-xl-7 col-lg-7 col-md-12 col-sm-12">


<div class="card">
    <div class="card-header card-colored-header bg-primary">
<div class="mt-0"><h4 class="card-title text-white mb-0">
<i class="ti ti-chart-pie"></i>    
Reporte <?= $fecha ?></h4></div>
</div>

    <div class="card-body">
        <div id="chart"></div>
        </div>
        </div>
        </div>



        <!-- TABLA - CUESTIONARIO -->
        <div class="col-xl-5 col-lg-5 col-md-12 col-sm-12">
        <div class="card">

            <div class="card-header  card-colored-header bg-primary">
<h4 class="mb-0 card-title text-white">
<i class="ti ti-user"></i>  
Encuesta de cliente</h4>
            </div>

            <div class="card-body p-0">
        <div style="max-height: 500px; overflow-y: auto;">
         <template x-if="clientes.length > 0">
        <div >
            <table class="table m-0 table-bordered table-striped  text-nowrap align-middle">
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
        </div>
</div>


 <div class="row">

        <template x-for="p in preguntas" :key="p.id">
            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
<div class="card">
    <div class="card-header crad-colored-header bg-primary">
   <div class="p-0 fw-bold">
                        <span class="fw-bold fs-5 card-title text-white" x-text="p.num_pregunta + '.-'"></span>
                        <span class="fw-bold fs-5 card-title text-white" x-text="p.pregunta"></span>
                    </div>

    </div>
    <div class="card-body">
                <div class="bg-white mt-4 p-2">

                 

                    <div :id="'chartPregunta' + p.id" style="height:250px;"></div>

                </div>
</div>
            </div>
            </div>
        </template>

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
                                       <!-------aqui se encuentra la tabla---->


        


            <table class="table table-bordered table-striped  text-nowrap align-middle">

            <thead>
                <tr>
                    <th>Pregunta</th>
                    <th class="text-center">Respuesta</th>
                </tr>
            </thead>

            <tbody>
                <template x-for="item in detalle.preguntas" :key="item.num_pregunta">

                <tr>
       
                    <td class="py-3"">
                        <span x-text="item.num_pregunta"></span>.-
                        <span x-text="item.pregunta"></span>
                    </td>

                     <td class="text-center py-3" 
                    >
                        <span ></span>

                        <span class="badge rounded-pill" 
                         :style="'background-color:' + getColor(item.resultado)" x-text="getTexto(item.resultado)"></span>
                    </td>
             
                </tr>
                </template>
            </tbody>
                
            </table>
        




        <div>
            <div class="form-label">Comentario:</div>
            <p style="font-size:1.1em" x-text="detalle.comentario"></p>
        </div>

      </div>
      <div class="modal-footer">
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