<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($titulo) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap');

        @page {
            margin: 0.8cm 0.8cm 1cm 0.8cm;
        }

        /* FUENTE ÚNICA MONTSERRAT PARA TODO EL DOCUMENTO */
        *, *::before, *::after, html, body, table, th, td, h1, h2, h3, h4, h5, h6, p, span, small, div {
            font-family: 'Montserrat', sans-serif !important;
        }

        body {
            font-size: 8pt;
            color: #212529;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th, td {
            border: 1px solid #dee2e6;
            padding: 4px 6px;
            vertical-align: middle;
        }

        .text-center { text-align: center !important; }
        .text-start { text-align: left !important; }
        .text-end { text-align: right !important; }
        .fw-bold { font-weight: 700 !important; }
        .fw-semibold { font-weight: 600 !important; }
        .text-muted { color: #6c757d; }
        .bg-light { background-color: #f8f9fa; }
        .table-primary { background-color: #cff4fc !important; }
        .table-dark { background-color: #212529; color: #ffffff; }

        .card-header-pdf {
            background-color: #0d6efd;
            color: #ffffff;
            font-weight: 700;
            padding: 5px 8px;
            font-size: 8.5pt;
            margin-bottom: 0;
            letter-spacing: 0.3px;
        }

        .section-table {
            margin-top: 0;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

    <!-- LOGO INSTITUCIONAL -->
    <?php if (!empty($logoBase64)): ?>
        <div style="text-align: right; margin-bottom: 8px;">
            <img src="<?= $logoBase64 ?>" style="width: 160px;">
        </div>
    <?php endif; ?>

    <!-- 1. INFORMACIÓN GENERAL -->
    <div class="card-header-pdf">INFORMACIÓN GENERAL</div>
    <table class="section-table">
        <tr>
            <td colspan="3" class="text-center bg-light" style="width: 35%;">Dep. Almacén</td>
            <td rowspan="3" class="text-center" style="width: 30%; background-color: #cfe2ff; color: #084298;">
                <h3 style="margin: 0; font-size: 10.5pt; font-weight: 700;">ORDEN DE COMPRA</h3>
            </td>
            <td class="text-center fw-semibold" style="width: 15%;">Cargo:</td>
            <td class="text-center fw-bold" style="width: 20%;"><?= htmlspecialchars($detalle['cargo'] ?? '') ?></td>
        </tr>
        <tr>
            <td colspan="3" class="text-center bg-light">Ref. Operativa</td>
            <td class="text-center fw-semibold">Fecha:</td>
            <td class="text-center fw-bold"><?= htmlspecialchars($detalle['fecha_fmt'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="text-center fw-semibold">Refacturación:</td>
            <td class="text-center fw-bold" colspan="2">
                <?= number_format((float)($detalle['porcentaje_total'] ?? 0), 2) ?> %
            </td>
            <td class="text-center fw-semibold">No. de control:</td>
            <td class="text-center fw-bold" style="color: #0d6efd; font-size: 9.5pt;"><?= htmlspecialchars($detalle['no_control'] ?? '') ?></td>
        </tr>
    </table>

    <!-- 2. DATOS DE LA ESTACIÓN -->
    <div class="card-header-pdf">DATOS DE LA ESTACIÓN</div>
    <table class="section-table">
        <tr>
            <th class="text-start bg-light" style="width: 130px;">Razón Social:</th>
            <td class="fw-semibold"><?= htmlspecialchars($detalle['estacion']['razon_social'] ?? 'Sin estación asignada') ?></td>
        </tr>
        <tr>
            <th class="text-start bg-light">RFC:</th>
            <td><?= htmlspecialchars($detalle['estacion']['rfc'] ?? 'S/I') ?></td>
        </tr>
        <tr>
            <th class="text-start bg-light">Dirección:</th>
            <td><?= htmlspecialchars($detalle['estacion']['direccion'] ?? 'S/I') ?></td>
        </tr>
    </table>

    <!-- 3. DATOS DEL PROVEEDOR -->
    <div class="card-header-pdf">DATOS DEL PROVEEDOR</div>
    <table class="section-table">
        <thead>
            <tr class="bg-light">
                <th class="text-center">Razón Social</th>
                <th class="text-center">Dirección</th>
                <th class="text-center">Contacto</th>
                <th class="text-center">Email</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($detalle['proveedores'])): ?>
                <?php foreach ($detalle['proveedores'] as $prov): ?>
                    <tr>
                        <td class="text-center"><?= htmlspecialchars($prov['razon_social']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($prov['direccion']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($prov['contacto']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($prov['email']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center text-muted">No se encontró información para mostrar.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- CUADRO COMPARATIVO Y REFACTURACIÓN -->
    <?php if (!empty($detalle['proveedores']) && count($detalle['proveedores']) > 2): ?>

        <!-- 4. CUADRO COMPARATIVO DE PROVEEDORES -->
        <div class="card-header-pdf">CUADRO COMPARATIVO DE PROVEEDORES</div>
        <table class="section-table">
            <thead>
                <tr class="bg-light text-center">
                    <th style="width: 75px;">Mejor Oferta</th>
                    <th class="text-start">Concepto</th>
                    <th style="width: 55px;">Unidades</th>
                    <th style="width: 65px;">Estatus</th>
                    <th class="text-end" style="width: 80px;">P. Unitario</th>
                    <th class="text-end" style="width: 75px;">Subtotal</th>
                    <th class="text-end" style="width: 40px;">IVA</th>
                    <th class="text-end" style="width: 85px;">Subtotal * IVA</th>
                    <th class="text-end" style="width: 80px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalle['proveedores'] as $prov): ?>
                    <tr>
                        <td class="text-center table-primary fw-bold" style="color: #055160;">
                            <?= ((int)$prov['check_p'] === 1) ? '[ X ]' : '[   ]' ?>
                        </td>
                        <td colspan="8" class="text-center table-primary fw-bold" style="color: #084298;">
                            Proveedor: <?= htmlspecialchars($prov['razon_social']) ?>
                        </td>
                    </tr>

                    <?php if (!empty($prov['articulos'])): ?>
                        <?php foreach ($prov['articulos'] as $aIdx => $art): ?>
                            <tr>
                                <td class="text-center text-muted fw-bold"><?= $aIdx + 1 ?></td>
                                <td><?= htmlspecialchars($art['concepto']) ?></td>
                                <td class="text-center"><?= $art['unidades'] ?></td>
                                <td class="text-center"><?= htmlspecialchars($art['estatus_r']) ?></td>
                                <td class="text-end">$ <?= number_format((float)$art['precio_unitario'], 2) ?></td>
                                <td class="text-end">$ <?= number_format((float)$art['subtotal'], 2) ?></td>
                                <td class="text-end">16%</td>
                                <td class="text-end">$ <?= number_format((float)$art['iva_fila'], 2) ?></td>
                                <td class="text-end fw-semibold">$ <?= number_format((float)$art['total_fila'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- TOTALES -->
                        <tr class="bg-light">
                            <td colspan="5" class="text-end fw-bold">SUMA</td>
                            <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)$prov['suma'], 2) ?></td>
                        </tr>
                        <tr class="bg-light">
                            <td colspan="5" class="text-end fw-bold">DESCUENTO</td>
                            <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)$prov['descuento'], 2) ?></td>
                        </tr>
                        <tr class="bg-light">
                            <td colspan="5" class="text-end fw-bold">ENVIO</td>
                            <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)$prov['envio_cp'], 2) ?></td>
                        </tr>
                        <tr class="bg-light">
                            <td colspan="5" class="text-end fw-bold">SUBTOTAL</td>
                            <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)$prov['subtotal_neto'], 2) ?></td>
                        </tr>
                        <tr class="bg-light">
                            <td colspan="5" class="text-end fw-bold">IVA</td>
                            <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)$prov['total_iva'], 2) ?></td>
                        </tr>
                        <tr class="table-dark">
                            <td colspan="5" class="text-end fw-bold">TOTAL A PAGAR</td>
                            <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)$prov['total_pagar'], 2) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No se encontró información para mostrar</td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 5. DATOS DE REFACTURACIÓN Y PRORRATEO -->
        <div class="card-header-pdf">DATOS DE REFACTURACIÓN Y PRORRATEO</div>
        <table class="section-table">
            <thead>
                <tr class="bg-light text-center">
                    <th>Prorrateo (Estación)</th>
                    <th>Descripción</th>
                    <th style="width: 55px;">Cantidad</th>
                    <th style="width: 75px;">Importe</th>
                    <th style="width: 65px;">Porcentaje</th>
                    <th style="width: 55px;">Estación</th>
                    <th style="width: 55px;">Almacén</th>
                    <th style="width: 80px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($detalle['refacturacion']['filas'])): ?>
                    <?php foreach ($detalle['refacturacion']['filas'] as $rf): ?>
                        <tr>
                            <td><?= htmlspecialchars($rf['estacion']) ?></td>
                            <td><?= htmlspecialchars($rf['descripcion']) ?></td>
                            <td class="text-center"><?= $rf['cantidad'] ?></td>
                            <td class="text-end">$ <?= number_format((float)$rf['importe'], 2) ?></td>
                            <td class="text-center"><?= number_format((float)$rf['porcentaje'], 0) ?> %</td>
                            <td class="text-center"><?= $rf['cantidadES'] ?></td>
                            <td class="text-center"><?= $rf['cantidadAl'] ?></td>
                            <td class="text-end fw-bold">$ <?= number_format((float)$rf['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">No se encontró información</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($detalle['refacturacion']['filas'])): ?>
                <tfoot>
                    <tr class="bg-light">
                        <td colspan="4" class="text-end fw-bold">SUMA</td>
                        <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)($detalle['refacturacion']['suma'] ?? 0), 2) ?></td>
                    </tr>
                    <tr class="bg-light">
                        <td colspan="4" class="text-end fw-bold">DESCUENTO</td>
                        <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)($detalle['refacturacion']['descuento'] ?? 0), 2) ?></td>
                    </tr>
                    <tr class="bg-light">
                        <td colspan="4" class="text-end fw-bold">ENVIO</td>
                        <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)($detalle['refacturacion']['envio'] ?? 0), 2) ?></td>
                    </tr>
                    <tr class="bg-light">
                        <td colspan="4" class="text-end fw-bold">SUBTOTAL</td>
                        <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)($detalle['refacturacion']['subtotal_neto'] ?? 0), 2) ?></td>
                    </tr>
                    <tr class="bg-light">
                        <td colspan="4" class="text-end fw-bold">IVA</td>
                        <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)($detalle['refacturacion']['iva'] ?? 0), 2) ?></td>
                    </tr>
                    <tr class="table-dark">
                        <td colspan="4" class="text-end fw-bold">TOTAL</td>
                        <td colspan="4" class="text-end fw-bold">$ <?= number_format((float)($detalle['refacturacion']['total_pagar'] ?? 0), 2) ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>

    <?php endif; ?>

    <!-- 6. FIRMAS DE CONFORMIDAD -->
    <?php if (!empty($detalle['firmas'])): ?>
        <table style="width: 100%; border: none; margin-top: 15px;" class="section-table">
            <tr>
                <?php foreach ($detalle['firmas'] as $firma): ?>
                    <td style="width: 50%; vertical-align: top; border: none; padding: 0 8px;">
                        <table style="width: 100%; margin-bottom: 0;">
                            <tr>
                                <th class="text-center" style="background-color: #0d6efd; color: #ffffff; padding: 4px; font-weight: 700;">
                                    <?= htmlspecialchars($firma['tipo_label']) ?>
                                </th>
                            </tr>
                            <tr>
                                <td class="text-center" style="height: 90px; padding: 5px;">
                                    <?php if ($firma['tipo_firma'] === 'A' && !empty($firma['base64_firma'])): ?>
                                        <img src="<?= $firma['base64_firma'] ?>" style="max-height: 80px; max-width: 180px;">
                                    <?php elseif ($firma['tipo_firma'] === 'B'): ?>
                                        <div style="font-size: 7.5pt; color: #555;">
                                            La solicitud de cheque se firmó por un medio electrónico.<br>
                                            <b style="color: #212529;">Fecha: <?= htmlspecialchars($firma['fecha']) ?></b>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-center fw-bold bg-light" style="padding: 4px; font-size: 7.5pt;">
                                    <?= htmlspecialchars($firma['nombre']) ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                <?php endforeach; ?>
            </tr>
        </table>
    <?php endif; ?>

</body>
</html>