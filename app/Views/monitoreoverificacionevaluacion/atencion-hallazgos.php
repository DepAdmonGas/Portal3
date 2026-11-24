<div id="container"
    data-module-station-key="sasisopa"
    data-estacion-id="<?= e($estacionId ?? '') ?>"
    x-data="{ ...actions(), ...atencionHallazgos()}">

    <?php if (empty($estacionId)): ?>

        <div id="sasisopa-empty-message"
            class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
            Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
        </div>

    <?php else: ?>

 <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-3 mt-3">
                        <?=
            !empty($permisos['crear']) ?
                '<button type="button" class="btn bg-primary-subtle text-primary mb-3" @click="nuevo()">
            <i class="ti ti-plus"></i> Nuevo
            </button>'
                : ''
            ?>  
                    </div>
                  </div>

        
      
        <div class="table-responsive">
            <table class="table table-bordered mb-3">
                <tr>
                    <td class="text-center align-middle"><img class="text-center" src="<?= asset('images/logos/Logo.png') ?>" style="width: 200px;"></td>
                    <td colspan="2" class="text-center align-middle"><b>Atención de Hallazgos</b></td>
                    <td class="text-center align-middle">Fo.ADMONGAS.018</td>
                </tr>
                <tr>
                    <td class="text-center align-middle">Realizado por: Nelly Estrada Garcia </td>
                    <td class="text-center align-middle">Revisado por: Eduardo Galicia Flores </td>
                    <td class="text-center align-middle">Autorizado por: Tomas Tarno Quinzaños </td>
                    <td class="text-center align-middle">Fecha de autorizacion 01/10/2018</td>
                </tr>
            </table>
        </div>


        <div class="datatables">
        <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
            <table id="table-atencion-hallazgos" class="table table-striped table-bordered text-nowrap align-middle">
                <thead>
                    <tr>
                        <th class="text-center align-middle" width="96px">#</th>
                        <th class="text-center">Fecha de la auditoria</th>
                        <th class="text-center">No de control de la auditoria</th>
                        <th class="text-center">Tipo de auditoria</th>
                        <th class="text-center" width="96px">
                            <i class="ti ti-dots-vertical fs-6"></i>
                        </th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

        </div>
        </div>
    <?php endif; ?>

</div>
