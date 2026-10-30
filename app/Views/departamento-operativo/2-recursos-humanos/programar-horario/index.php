<div id="container" class="mt-4 mb-5"
     data-id-estacion="<?= $ctx['id_estacion'] ?? '' ?>"
     data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
     data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
     data-id-gas="<?= $idGas ?>"
     data-module-station-key="<?= $moduleStationKey ?>"
     x-data="{ ...actions(), ...programarHorarioComponent() }">

    <div class="row">

       <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-3"  id="ph-agregar-wrapper" >
                      <button type="button" class="btn bg-primary-subtle text-primary"
                    @click="agregarReporte()">
                <i class="ti ti-plus me-1"></i> Nuevo
            </button>
                    </div>
                  </div>

        
            


        <div class="datatables">
            <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
                <table id="tabla-programar-horario" class="table table-striped table-bordered mb-0 text-nowrap align-middle portal-table portal-table--selectable">
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>
</div>
