<div id="container" class="mt-3 mb-5"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? 'formato-descarga-merma', ENT_QUOTES, 'UTF-8') ?>"
x-data="{ ...actions() }">

<div class="row">
    <div class="col-12 mb-3">
        <!-- Se cambió justify-content-between por justify-content-end -->
        <div class="d-flex justify-content-end align-items-center ">
            <div class="d-flex gap-2">
                <?php if ($puedeDescargar): ?>
                    <a href="/departamento-operativo/importacion/formato-descarga-merma/pdf/<?= $registro['id'] ?>"
                       class="btn btn-outline-danger" target="_blank">
                        <i class="ti ti-file-type-pdf me-1"></i> Descargar PDF
                    </a>
                    <a href="/departamento-operativo/importacion/formato-descarga-merma/excel/<?= $registro['id'] ?>"
                       class="btn btn-outline-success">
                        <i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<div class="row">

<!---------- CARD DATOS GENERALES ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-file-text"></i> DATOS GENERALES</h4>
</div>

<div class="card-body">
<div class="row g-3">

<div class="col-12 col-md-2">
<label class="form-label">Folio:</label>
<div class="">00<?= htmlspecialchars($registro['folio']) ?></div>
</div>

<div class="col-12 col-md-3">
<label class="form-label">Estación de descarga:</label>
<div class=""><?= htmlspecialchars($registro['estacion']) ?></div>
</div>

<div class="col-12 col-md-3">
<label class="form-label">Responsable de la estación:</label>
<div class=""><?= htmlspecialchars($registro['responsable']) ?></div>
</div>

<div class="col-12 col-md-4">
<label class="form-label">Fecha y hora de la descarga de full:</label>
<div class=""><?= htmlspecialchars($registro['fecha_hora_llegada']) ?></div>
</div>

<div class="col-12 col-md-6">
<label class="form-label">Producto recibido:</label>
<div class=""><?= htmlspecialchars($registro['producto']) ?></div>
</div>

<div class="col-12 col-md-6">
<label class="form-label">Número de factura o remisión:</label>
<div class=""><?= htmlspecialchars($registro['no_factura_remision']) ?></div>
</div>

<div class="col-12 col-md-2">
<label class="form-label">Litros:</label>
<div class=""><?= $registro['litros'] ?></div>
</div>

<div class="col-12 col-md-2">
<label class="form-label">Precio por litro:</label>
<div class="">$<?= $registro['precio_litro'] ?></div>
</div>

<div class="col-12 col-md-2">
<label class="form-label">Cuenta litros:</label>
<div class=""><?= $registro['cuenta_litros'] ?></div>
</div>

<div class="col-12 col-md-2">
<label class="form-label">Merma (Lts):</label>
<div class=""><?= $registro['merma'] ?> lts</div>
</div>

<div class="col-12 col-md-2">
<label class="form-label">N.C:</label>
<div class=""><?= $registro['nc'] ?> </div>
</div>

<div class="col-12 col-md-2">
<label class="form-label">Importe N.C:</label>
<div class="">$<?= $registro['importe_nc'] ?></div>
</div>


<div class="col-12 col-md-3">
<label class="form-label">Unidad:</label>
<div class=""><?= htmlspecialchars($registro['unidad']) ?></div>
</div>

<div class="col-12 col-md-6">
<label class="form-label">Nombre del operador de la unidad:</label>
<div class=""><?= htmlspecialchars($registro['operador']) ?></div>
</div>

<div class="col-12 col-md-3">
<label class="form-label">Compañia a la que pertenece el transportista:</label>
<div class=""><?= htmlspecialchars($registro['transportista']) ?></div>
</div>

<div class="col-12 col-md-6 text-center">
<label class="form-label mt-2">Sellos Alterados:</label>
<div class=""><?= htmlspecialchars($registro['sellos']) ?></div>
</div>

<div class="col-12 col-md-6 text-center">
<label class="form-label mt-2">Se detuvo la venta durante la descarga:</label>
<div class=""><?= htmlspecialchars($registro['detuvo_venta']) ?></div>
</div>

</div>

</div>
</div>

</div>

<!---------- CARD DOCUMENTOS ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-file-text"></i> DOCUMENTACIÓN</h4>
</div>

<div class="card-body">

<?php
$adjuntoFields = [
'no_factura'         => 'Factura / Remisión',
'inventario_inicial' => 'Inventario Inicial',
'nice'               => 'Nice',
'inventario_final'   => 'Inventario Final',
'metro_contador'     => 'Metro Contador',
'metro_contador20'   => 'Metro Contador 20°',
];
$hasAdjuntos = false;
foreach ($adjuntoFields as $campo => $label) {
if (!empty($registro[$campo])) { $hasAdjuntos = true; break; }
}
?>

<?php if ($hasAdjuntos): ?>
<div class="row g-4">
    <?php foreach ($adjuntoFields as $campo => $label): ?>
        <?php if (!empty($registro[$campo])): ?>
            <?php
            $url = \App\Services\FormatoDescargaMermaService::getAdjuntoUrl($registro[$campo]);
            $nombreArchivo = basename($registro[$campo]);
            $ext = strtolower(pathinfo($registro[$campo], PATHINFO_EXTENSION));
            $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $isPdf = ($ext === 'pdf');
            ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card h-100 bg-primary-subtle">
                    
                    <!-- Encabezado de la Tarjeta -->
                    <div class="card-header  d-flex justify-content-between align-items-center py-2 px-3 border-bottom-0">
                        <span class="fw-semibold text-truncate" title="<?= htmlspecialchars($label) ?>">
                            <?= htmlspecialchars($label) ?>
                        </span>
                        <?php if ($url): ?>
                            <!-- Botón superior para descargar -->
                            <a href="<?= $url ?>" download="<?= htmlspecialchars($nombreArchivo) ?>" class="btn btn-sm btn-icon btn-ghost-secondary rounded-circle" data-bs-toggle="tooltip" title="Descargar archivo">
                                <i class="ti ti-download fs-5"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Cuerpo de la Tarjeta -->
                    <div class="card-body p-3 d-flex align-items-center justify-content-center bg-white rounded-bottom mx-2 mb-2 border" style="min-height: 180px;">
                        <?php if ($isImg && $url): ?>
                            <!-- Las imágenes mantienen la vista previa visual -->
                            <a href="<?= $url ?>" target="_blank" class="w-100 h-100 d-flex align-items-center justify-content-center" title="Clic para ver completa">
                                <img src="<?= $url ?>" class="img-fluid rounded object-fit-contain" style="max-height: 180px;" alt="<?= htmlspecialchars($label) ?>" loading="lazy">
                            </a>

                        <?php elseif ($isPdf && $url): ?>
                            <!-- Para PDF: Descarga directa -->
                            <div class="text-center py-3">
                                <i class="ti ti-file-type-pdf text-danger d-block mb-2" style="font-size: 6rem;"></i>
                           
                            </div>

                        <?php else: ?>
                            <!-- Para otros formatos: Descarga directa -->
                            <div class="text-center py-3 text-muted">
                                <i class="ti ti-file-text d-block mb-2" style="font-size: 6rem;"></i>
                     
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>


</div>

</div>
</div>

<!---------- CARD FIRMAS ---------->
<div class="col-12">

<?php if (!empty($registro['firmas'])): ?>
<div class="row g-3">
    <?php foreach ($registro['firmas'] as $tipo => $archivo): ?>
        <?php $firmaUrl = \App\Services\FormatoDescargaMermaService::getFirmaUrl($archivo); ?>
        <div class="col-md-6">
            <div class="card border-0 bg-white  h-100">
                
                <!-- Encabezado con ícono y tipo de firma dinámico -->
                <div class="card-header text-bg-primary py-3 border-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                                <i class="ti ti-signature fs-6"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="mb-0 text-white text-uppercase"><?= htmlspecialchars($tipo) ?></h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cuerpo de la tarjeta con contenedor punteado -->
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div class="signature-pad-wrapper w-100 d-flex align-items-center justify-content-center bg-white-subtle" style="min-height: 250px;">
                        <?php if ($firmaUrl): ?>
                            <img src="<?= $firmaUrl ?>" class="img-fluid p-2" style="max-height: 150px; object-fit: contain;" alt="Firma <?= htmlspecialchars($tipo) ?>">
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="ti ti-signature-off fs-1 d-block mb-1 opacity-50"></i>
                                <span class="fw-semibold small">Sin firma registrada</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</div>


</div>





</div>



</div>
