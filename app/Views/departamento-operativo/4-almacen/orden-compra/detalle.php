<div class="mt-3 pb-5">

    <div class="row g-3">

        <!---------- 1. INFORMACIÓN GENERAL ---------->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header text-bg-primary">
                    <h5 class="mb-0 text-white d-flex align-items-center">
                        <i class="ti ti-info-circle me-2"></i> INFORMACIÓN GENERAL
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 align-middle">
                            <tr>
                                <td colspan="3" class="text-center align-middle">Dep. Almacén</td>
                                <td rowspan="3" class="text-center align-middle">
                                    <h5 class="mb-0 fw-bold">ORDEN DE COMPRA</h5>
                                </td>
                                <td class="text-center align-middle fw-semibold">Cargo:</td>
                                <td class="text-center align-middle fw-bold"><?= htmlspecialchars($detalle['cargo'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-center align-middle">Ref. Operativa</td>
                                <td class="text-center align-middle fw-semibold">Fecha:</td>
                                <td class="text-center align-middle fw-bold"><?= htmlspecialchars($detalle['fecha_fmt'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <td class="text-center align-middle fw-semibold">Refacturación:</td>
                                <td class="text-center align-middle fw-bold" colspan="2">
                                    <?= number_format((float)($detalle['porcentaje_total'] ?? 0), 2) ?> %
                                </td>
                                <td class="text-center align-middle fw-semibold">No. de control:</td>
                                <td class="text-center align-middle fw-bold"><?= htmlspecialchars($detalle['no_control'] ?? '') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!---------- 2. DATOS DE LA ESTACIÓN ---------->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header text-bg-primary">
                    <h5 class="mb-0 text-white d-flex align-items-center">
                        <i class="ti ti-gas-station me-2"></i> DATOS DE LA ESTACIÓN
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0 w-100">
                            <tr>
                                <th class="text-start align-middle" width="200px">Razón Social:</th>
                                <td class="fw-semibold"><?= htmlspecialchars($detalle['estacion']['razon_social'] ?? 'Sin estación asignada') ?></td>
                            </tr>
                            <tr>
                                <th class="text-start align-middle">RFC:</th>
                                <td><?= htmlspecialchars($detalle['estacion']['rfc'] ?? 'S/I') ?></td>
                            </tr>
                            <tr>
                                <th class="text-start align-middle">Dirección:</th>
                                <td><?= htmlspecialchars($detalle['estacion']['direccion'] ?? 'S/I') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!---------- 3. DATOS DEL PROVEEDOR ---------->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header text-bg-primary">
                    <h5 class="mb-0 text-white d-flex align-items-center">
                        <i class="ti ti-truck me-2"></i> DATOS DEL PROVEEDOR
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center align-middle">Razón Social</th>
                                    <th class="text-center align-middle">Dirección</th>
                                    <th class="text-center align-middle">Contacto</th>
                                    <th class="text-center align-middle">Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($detalle['proveedores'])): ?>
                                    <?php foreach ($detalle['proveedores'] as $prov): ?>
                                        <tr>
                                            <td class="text-center align-middle"><?= htmlspecialchars($prov['razon_social']) ?></td>
                                            <td class="text-center align-middle"><?= htmlspecialchars($prov['direccion']) ?></td>
                                            <td class="text-center align-middle"><?= htmlspecialchars($prov['contacto']) ?></td>
                                            <td class="text-center align-middle"><?= htmlspecialchars($prov['email']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-primary py-3">
                                            No se encontró información para mostrar.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONDICIONAL: SI HAY MÁS DE 2 PROVEEDORES REGISTRADOS -->
        <?php if (!empty($detalle['proveedores']) && count($detalle['proveedores']) > 2): ?>

            <!---------- 4. CUADRO COMPARATIVO DE PROVEEDORES ---------->
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header text-bg-primary">
                        <h5 class="mb-0 text-white d-flex align-items-center">
                            <i class="ti ti-table me-2"></i> CUADRO COMPARATIVO DE PROVEEDORES
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0 align-middle w-100">
                                <thead>
                                    <tr class="text-center align-middle">
                                        <th class="text-center align-middle" width="120px">Mejor Oferta</th>
                                        <th class="text-start align-middle">Concepto</th>
                                        <th class="text-center align-middle" width="85px">Unidades</th>
                                        <th class="text-center align-middle" width="100px">Estatus</th>
                                        <th class="text-end align-middle" width="125px">Precio Unitario</th>
                                        <th class="text-end align-middle" width="125px">Subtotal</th>
                                        <th class="text-end align-middle" width="75px">IVA</th>
                                        <th class="text-end align-middle" width="160px">Total (Subtotal * IVA)</th>
                                        <th class="text-end align-middle" width="130px">Total</th>
                                    </tr>
                                </thead>

                                <?php foreach ($detalle['proveedores'] as $prov): ?>
                                    <tbody>
                                        <!-- CABECERA CELESTE DE PROVEEDOR -->
                                        <tr>
                                            <td class="text-center align-middle table-primary">
                                                <div class="form-check d-flex justify-content-center m-0">
                                                    <input class="form-check-input" 
                                                           type="radio" 
                                                           name="radioMejorOferta_<?= $prov['id'] ?>" 
                                                           <?= (int)$prov['check_p'] === 1 ? 'checked' : '' ?> 
                                                           disabled
                                                           style="transform: scale(1.2); border: 1px solid #454646 !important;">
                                                </div>
                                            </td>
                                            <td colspan="8" class="fw-semibold text-center align-middle table-primary">
                                                Nombre del proveedor: <?= htmlspecialchars($prov['razon_social']) ?>
                                            </td>
                                        </tr>

                                        <!-- ARTÍCULOS DEL PROVEEDOR -->
                                        <?php if (!empty($prov['articulos'])): ?>
                                            <?php foreach ($prov['articulos'] as $aIdx => $art): ?>
                                                <tr>
                                                    <td class="text-center fw-semibold text-muted"><?= $aIdx + 1 ?></td>
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

                                            <!-- TOTALES DEL PROVEEDOR -->
                                            <tr class="table-light">
                                                <td colspan="5" class="text-end align-middle">SUMA</td>
                                                <td colspan="4" class="align-middle text-end fw-semibold">$ <?= number_format((float)$prov['suma'], 2) ?></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="5" class="text-end align-middle">DESCUENTO</td>
                                                <td colspan="4" class="align-middle text-end fw-semibold">$ <?= number_format((float)$prov['descuento'], 2) ?></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="5" class="text-end align-middle">ENVIO</td>
                                                <td colspan="4" class="align-middle text-end fw-semibold">$ <?= number_format((float)$prov['envio_cp'], 2) ?></td>
                                            </tr>
                                            <tr class="table-light">
                                                <th colspan="5" class="text-end align-middle">SUBTOTAL</th>
                                                <th colspan="4" class="align-middle text-end fw-semibold">$ <?= number_format((float)$prov['subtotal_neto'], 2) ?></th>
                                            </tr>
                                            <tr class="table-light">
                                                <th colspan="5" class="text-end align-middle">IVA</th>
                                                <th colspan="4" class="align-middle text-end fw-semibold">$ <?= number_format((float)$prov['total_iva'], 2) ?></th>
                                            </tr>
                                            <tr class="table-dark">
                                                <th colspan="5" class="text-end align-middle text-white">TOTAL A PAGAR</th>
                                                <th colspan="4" class="align-middle text-end fw-semibold text-white">$ <?= number_format((float)$prov['total_pagar'], 2) ?></th>
                                            </tr>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-primary py-2">
                                                    No se encontró información para mostrar
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!---------- 5. DATOS DE REFACTURACIÓN Y PRORRATEO ---------->
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header text-bg-primary">
                        <h5 class="mb-0 text-white d-flex align-items-center">
                            <i class="ti ti-calculator me-2"></i> DATOS DE REFACTURACIÓN Y PRORRATEO
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th class="text-center align-middle">Prorrateo (Estación)</th>
                                        <th class="text-center align-middle">Descripción</th>
                                        <th class="text-center align-middle">Cantidad</th>
                                        <th class="text-center align-middle">Importe</th>
                                        <th class="text-center align-middle">Porcentaje</th>
                                        <th class="text-center align-middle">Estación</th>
                                        <th class="text-center align-middle">Almacén</th>
                                        <th class="text-center align-middle">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($detalle['refacturacion']['filas'])): ?>
                                        <?php foreach ($detalle['refacturacion']['filas'] as $rf): ?>
                                            <tr>
                                                <td class="text-center align-middle"><?= htmlspecialchars($rf['estacion']) ?></td>
                                                <td class="text-center align-middle"><?= htmlspecialchars($rf['descripcion']) ?></td>
                                                <td class="text-center align-middle"><?= $rf['cantidad'] ?></td>
                                                <td class="text-center align-middle">$ <?= number_format((float)$rf['importe'], 2) ?></td>
                                                <td class="text-center align-middle"><?= number_format((float)$rf['porcentaje'], 0) ?> %</td>
                                                <td class="text-center align-middle"><?= $rf['cantidadES'] ?></td>
                                                <td class="text-center align-middle"><?= $rf['cantidadAl'] ?></td>
                                                <td class="text-center align-middle fw-semibold">$ <?= number_format((float)$rf['total'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-primary py-3">No se encontró información</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>

                                <?php if (!empty($detalle['refacturacion']['filas'])): ?>
                                    <tfoot>
                                        <tr class="table-light">
                                            <th colspan="4" class="text-end align-middle">SUMA</th>
                                            <th colspan="4" class="text-end align-middle fw-semibold">$ <?= number_format((float)($detalle['refacturacion']['suma'] ?? 0), 2) ?></th>
                                        </tr>
                                        <tr class="table-light">
                                            <th colspan="4" class="text-end align-middle">DESCUENTO</th>
                                            <th colspan="4" class="text-end align-middle fw-semibold">$ <?= number_format((float)($detalle['refacturacion']['descuento'] ?? 0), 2) ?></th>
                                        </tr>
                                        <tr class="table-light">
                                            <th colspan="4" class="text-end align-middle">ENVIO</th>
                                            <th colspan="4" class="text-end align-middle fw-semibold">$ <?= number_format((float)($detalle['refacturacion']['envio'] ?? 0), 2) ?></th>
                                        </tr>
                                        <tr class="table-light">
                                            <th colspan="4" class="text-end align-middle">SUBTOTAL</th>
                                            <th colspan="4" class="text-end align-middle fw-semibold">$ <?= number_format((float)($detalle['refacturacion']['subtotal_neto'] ?? 0), 2) ?></th>
                                        </tr>
                                        <tr class="table-light">
                                            <th colspan="4" class="text-end align-middle">IVA</th>
                                            <th colspan="4" class="text-end align-middle fw-semibold">$ <?= number_format((float)($detalle['refacturacion']['iva'] ?? 0), 2) ?></th>
                                        </tr>
                                        <tr class="table-dark">
                                            <th colspan="4" class="text-end align-middle text-white">TOTAL</th>
                                            <th colspan="4" class="text-end align-middle text-white">$ <?= number_format((float)($detalle['refacturacion']['total_pagar'] ?? 0), 2) ?></th>
                                        </tr>
                                    </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

<!---------- 6. SECCIÓN DE FIRMAS REGISTRADAS ---------->
        <?php if (!empty($detalle['firmas'])): ?>
                            <?php foreach ($detalle['firmas'] as $firma): ?>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        
                                        <!-- Header con el diseño moderno (icono redondo) -->
                                        <div class="card-header bg-primary text-white py-3 border-0">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width:45px;height:45px;">
                                                    <i class="<?= ($firma['tipo_firma'] === 'B') ? 'ti ti-shield-check' : 'ti ti-user-check' ?> fs-6"></i>
                                                </div>
                                                <div class="ms-3 overflow-hidden">
                                                    <h6 class="mb-0 text-white text-truncate"><?= htmlspecialchars($firma['tipo_label']) ?></h6>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Cuerpo de la tarjeta con tu lógica original intacta -->
                                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                                            <?php if ($firma['tipo_firma'] === 'A'): ?>
                                                <img src="/uploads/firmas/orden-compra/<?= htmlspecialchars($firma['firma']) ?>" 
                                                     alt="Firma <?= htmlspecialchars($firma['tipo_label']) ?>" 
                                                     class="img-fluid mb-2" 
                                                     style="max-height: 90px; object-fit: contain;"
                                                     onerror="this.style.display='none'">
                                            <?php elseif ($firma['tipo_firma'] === 'B'): ?>
                                                <div class="p-2">
                                                    <i class="ti ti-shield-check text-success fs-1 mb-2"></i>
                                                    <small class="d-block text-secondary">
                                                        La solicitud de cheque se firmó por un medio electrónico.
                                                    </small>
                                                    <small class="fw-bold d-block text-dark mt-1">
                                                        Fecha: <?= htmlspecialchars($firma['fecha']) ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Footer con el nombre -->
                                        <div class="card-footer bg-light text-center py-2">
                                            <h6 class="mb-0 fw-semibold text-truncate"><?= htmlspecialchars($firma['nombre']) ?></h6>
                                        </div>

                                    </div>
                                </div>
                            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</div>