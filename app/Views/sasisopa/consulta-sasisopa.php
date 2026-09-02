<div id="container" class="pb-4"
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

<table class="table table-sm table-bordered table-striped table-hover mt-4">

        <thead>

            <tr>
                <th class="text-center align-middle bg-primary text-white">
                    #
                </th>
                <th class="text-center align-middle bg-primary text-white">
                    Permiso CRE
                </th>
                <th class="text-center align-middle bg-primary text-white">
                    Estación
                </th>
                <th class="text-center align-middle bg-primary text-white">
                    Versión
                </th>
                <th
                    width="35"
                    class="text-center align-middle bg-primary text-white">
                    <i class="ti ti-file-type-pdf fs-7"></i>
                </th>
            </tr>
        </thead>
        <tbody>
            <template
                x-if="lista.length === 0">
                <tr>
                    <td
                        colspan="5"
                        class="text-center">
                        <small>
                            No se encontró información para mostrar
                        </small>
                    </td>
                </tr>
            </template>
            <template
                x-for="item in lista"
                :key="item.id">
                <tr>
                    <td
                        class="text-center align-middle fw-bolder"
                        x-text="item.id">
                    </td>
                    <td
                        class="text-center align-middle"
                        x-text="item.permisocre">
                    </td>
                    <td
                        class="text-center align-middle"
                        x-text="item.razonsocial">
                    </td>
                    <td
                        class="text-center align-middle"
                        x-text="item.version">
                    </td>

                    <td>
                        <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            :href="item.documento" download>
                            <i class="fs-4 ti ti-file-type-pdf fs-7 text-danger"></i>
                            </a>
                    </td>


                </tr>

            </template>

        </tbody>

    </table>

    </div>

    <?php endif; ?>

</div>