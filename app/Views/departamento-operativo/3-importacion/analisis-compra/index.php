<div id="container-analisis-compra" class="mt-2 mb-5"
     data-id-usuario="<?= (int)$idUsuario ?>"
     data-id-estacion="<?= (int)$idEstacion ?>"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
     data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
     data-id-year="<?= (int)$idYear ?>"
     data-id-mes="<?= (int)$idMes ?>"
     x-data="{ ...actions(), ...analisisCompraComponent() }">

    <?php if (empty($idEstacion)): ?>

        <div id="analisis-compra-empty-message"
            class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
            Debes de seleccionar una estación del menú superior para poder visualizar el Análisis de Compra.
        </div>

    <?php else: ?>

        <?= $analisisHtml ?>

    <?php endif; ?>
</div>
