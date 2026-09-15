<div id="container" class="mt-4 mb-5"
     data-id="<?= (int)$id ?>"
     data-fecha="<?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') ?>"
     data-estatus="<?= (int)$estatus ?>"
     data-modulo="detalle">

    <?php if (!empty($filas)): ?>
        <div class="row">
            <?php $fecha_tarjeta = $fecha_larga; ?>
            <?php foreach ($filas as $fila): ?>
                <?php
                    $tarjetaEditable = false;
                    $tarjetaMostrarAcciones = false;
                    include __DIR__ . '/_tarjeta.php';
                ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info d-flex align-items-center" role="alert">
            <i class="ti ti-info-circle fs-5 me-2"></i>
            <div>No se encontraron descargas registradas para este formato.</div>
        </div>
    <?php endif; ?>

    <!-- Modal Visor de Imagen -->
    <div class="modal fade" id="modalVisorImagen" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-photo me-2"></i>Imagen de descarga</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imgVisor" src="" alt="Imagen de descarga" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>

</div>