<div id="container" class="pb-4 mt-3"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...consulta()}">

<?php if (empty($estacionId)): ?>

    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

    <div id="sasisopa-content">

<div class="table-responsive">
<table id="table-consulta-sasisopa" class="table table-bordered table-striped mb-0">

        <thead>

            <tr>
                <th class="text-center align-middle">
                    #
                </th>
                <th class="text-center align-middle">
                    Permiso CRE
                </th>
                <th class="text-center align-middle">
                    Estación
                </th>
                <th class="text-center align-middle">
                    Versión
                </th>
                <th
                    width="35"
                    class="text-center align-middle">
                    <i class="ti ti-file-type-pdf fs-7"></i>
                </th>
            </tr>
        </thead>
        <tbody></tbody>

    </table>
</div>

    </div>

    <?php endif; ?>

</div>
