<div id="container" class="mt-3 mb-5"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? 'formato-descarga-merma', ENT_QUOTES, 'UTF-8') ?>"
x-data="{ ...actions() }">


        <!-- Se cambió justify-content-between por justify-content-end -->

                <?php if ($puedeDescargar): ?>
    <div class="row g-2 ms-0 ms-md-auto mb-3 w-100 justify-content-between">

        <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
            <a href="/departamento-operativo/importacion/formato-descarga-merma/pdf/<?= $registro['id'] ?>"
               class="btn btn-outline-danger" data-merma-export="pdf">
                <i class="ti ti-file-type-pdf me-1"></i> Descargar PDF
            </a>
        </div>

        <div class="col-12 col-md-auto d-grid">
            <a href="/departamento-operativo/importacion/formato-descarga-merma/excel/<?= $registro['id'] ?>"
               class="btn btn-outline-success" data-merma-export="xlsx">
                <i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel
            </a>
        </div>

    </div>
<?php endif; ?>
      

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
?>

<div class="row g-4">
    <?php foreach ($adjuntoFields as $campo => $label): ?>
        <?php
        $nombreArchivo = (string)($registro[$campo] ?? '');
        $url = \App\Services\FormatoDescargaMermaService::getAdjuntoUrl($nombreArchivo);
        $ext = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
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
                                <i class="ti ti-download fs-6"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted" title="Documento no disponible">
                                <i class="ti ti-file-off fs-6"></i>
                            </span>
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

                        <?php elseif ($url): ?>
                            <!-- Para otros formatos: Descarga directa -->
                            <div class="text-center py-3 text-muted">
                                <i class="ti ti-file-text d-block mb-2" style="font-size: 6rem;"></i>
                     
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="ti ti-file-off fs-6 d-block mb-2"></i>
                                <span>Documento no disponible</span>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
    <?php endforeach; ?>
</div>


</div>

</div>
</div>

<!---------- CARD FIRMAS ---------->
<div class="col-12">

<div class="row g-3">
    <?php foreach (['Encargado de estación', 'Operador'] as $tipo): ?>
        <?php
        $firmas = $registro['firmas'] ?? [];
        $firmaRegistrada = array_key_exists($tipo, $firmas);
        $archivo = $firmas[$tipo] ?? '';
        $firmaUrl = \App\Services\FormatoDescargaMermaService::getFirmaUrl($archivo);
        $nombreFirmante = $tipo === 'Encargado de estación'
            ? ($registro['responsable'] ?? '')
            : ($registro['operador'] ?? '');
        ?>
        <div class="col-md-6">
            <div class="card border h-100">
                <div class="card-header bg-primary text-white py-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                            <i class="ti <?= $firmaRegistrada ? 'ti-user-check' : 'ti-clock-hour-4' ?> fs-6"></i>
                        </div>
                        <div class="ms-3 overflow-hidden">
                            <h6 class="mb-0 text-white text-uppercase"><?= htmlspecialchars($tipo === 'Encargado de estación' ? 'Encargado de estación' : 'Operador') ?></h6>
                        </div>
                    </div>
                </div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                    <?php if ($firmaUrl): ?>
                        <img src="<?= $firmaUrl ?>" class="img-fluid mb-3" style="max-height:90px;object-fit:contain;" alt="Firma <?= htmlspecialchars($tipo) ?>">
                    <?php else: ?>
                        <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                        <?php if ($firmaRegistrada): ?>
                            <small class="text-muted">La firma está registrada, pero su imagen no está disponible.</small>
                        <?php else: ?>
                            <h6 class="text-muted mb-0">Sin firma registrada</h6>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light text-center">
                    <h6 class="mb-0 fw-semibold text-truncate"><?= htmlspecialchars($nombreFirmante) ?></h6>
                    <?php if (!$firmaRegistrada): ?><small class="text-muted">Pendiente de firma</small><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</div>


</div>





</div>



</div>
