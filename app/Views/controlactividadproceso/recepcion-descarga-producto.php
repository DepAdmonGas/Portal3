<div id="container" class="mb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
    x-data="{...actions(), ...recepcionDescargaProducto()}">

    <?php if (empty($estacionId)): ?>

        <div id="sasisopa-empty-message"
            class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
            Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
        </div>

    <?php else: ?>

        <div class="text-end mb-3 mt-3">
            <div class="btn-group">
                <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical fs-4"></i>
                </button>
                <ul class="dropdown-menu animated rubberBand">
                    <?= !empty($permisos['crear']) ?
                        '<li><a class="dropdown-item pointer" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>'
                        : ''
                    ?>
                    <li>
                        <a class="dropdown-item pointer" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="datatables">
            <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
                <table id="table-recepcion-descarga-producto" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Hora llegada</th>
                            <th>Hora salida</th>
                            <th>Vehículo (Placas)</th>
                            <th>Operador</th>
                            <th>No. Factura</th>
                            <th>Litros compra</th>
                            <th>Producto</th>
                            <th>Persona que recibe</th>
                            <th>Persona que superviso</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>



        <!-- MODAL BUSCAR -->
        <div
            class="modal fade"
            id="ModalBuscar"
            tabindex="-1"
            aria-hidden="true"
            data-bs-backdrop="static"
            data-bs-keyboard="false">

            <div class="modal-dialog modal-lg modal-dialog-centered">

                <div class="modal-content">
                    <div class="modal-header modal-colored-header bg-primary text-white">

                        <h4 class="modal-title text-white">
                            <i class="ti ti-search"></i>
                            Buscar
                        </h4>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"
                            @click="limpiar()">
                        </button>

                    </div>

                    <!-- BODY -->
                    <div class="modal-body">


                        <!-- YEAR -->

                        <label class="form-label mt-2">* Año:</label>
                        <select
                            class="form-select mb-3"
                            x-model="filtros.year"
                            :class="errors.year ? 'is-invalid' : ''"
                            @input="errors.year = false">

                            <option value="">
                                Selecciona una opción...
                            </option>

                            <template x-for="year in years">

                                <option
                                    :value="year"
                                    x-text="year">
                                </option>

                            </template>

                        </select>

                        <!-- MES -->
                        <label class="form-label mt-2">Mes:</label>

                        <select
                            class="form-select"
                            x-model="filtros.mes">

                            <option value="">
                                Todos
                            </option>

                            <option value="1">Enero</option>
                            <option value="2">Febrero</option>
                            <option value="3">Marzo</option>
                            <option value="4">Abril</option>
                            <option value="5">Mayo</option>
                            <option value="6">Junio</option>
                            <option value="7">Julio</option>
                            <option value="8">Agosto</option>
                            <option value="9">Septiembre</option>
                            <option value="10">Octubre</option>
                            <option value="11">Noviembre</option>
                            <option value="12">Diciembre</option>

                        </select>

                    </div>

                    <!-- FOOTER -->
                    <div class="modal-footer">

                        <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                        <button
                            class="btn bg-danger-subtle text-danger"
                            data-bs-dismiss="modal"
                            @click="limpiar()">
                            <i class="ti ti-x"></i> Cancelar
                        </button>

                    </div>
                     <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                        <button
                            class="btn btn-success"
                            @click="buscar()">
                            <i class="ti ti-search"></i> Buscar
                        </button>
                    </div>
                  </div>

                      
                      
                    </div>

                </div>

            </div>

        </div>

        <!-- Modal detalle -->

        <div
            class="modal fade"
            id="modalDetalle"
            tabindex="-1" 
            data-bs-backdrop="static"
            data-bs-keyboard="false">

            <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header modal-colored-header bg-primary text-white">

                        <h4 class="modal-title text-white">
                            Folio:
                            <span x-text="detalle.folio"></span>
                        </h4>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                        </button>

                    </div>


                    <div class="modal-body">

                        <h5 class="mb-3">Información General</h5>
                        <div class="row g-2">
                            <div class="col-12 col-md-3 mb-3"><label class="form-label mb-1">Fecha:</label><div x-text="detalle.fecha?.detalle"></div></div>
                            <div class="col-6 col-md-3 mb-3"><label class="form-label mb-1">Hora de llegada:</label><div x-text="detalle.hora_llegada?.display"></div></div>
                            <div class="col-6 col-md-3 mb-3"><label class="form-label mb-1">Hora de salida:</label><div x-text="detalle.hora_salida?.display"></div></div>
                            <div class="col-12 col-md-3 mb-3"><label class="form-label mb-1">Tiempo de descarga:</label><div x-text="detalle.tiempo_descarga"></div></div>
                            <div class="col-12 col-md-4 mb-3"><label class="form-label mb-1">Factura:</label><div x-text="detalle.no_factura"></div></div>
                            <div class="col-12 col-md-4 mb-3"><label class="form-label mb-1">Producto:</label><div :style="`color:${detalle.producto?.color}`" x-text="detalle.producto?.nombre"></div></div>
                            <div class="col-12 col-md-4 mb-3"><label class="form-label mb-1">Litros de compra:</label><div x-text="detalle.litros_compra?.display"></div></div>
                            <div class="col-12 col-md-3 mb-3"><label class="form-label mb-1">Operador:</label><div x-text="detalle.operador"></div></div>
                            <div class="col-12 col-md-3 mb-3"><label class="form-label mb-1">Vehículo (placas):</label><div x-text="detalle.placa"></div></div>
                            <div class="col-12 col-md-3 mb-3"><label class="form-label mb-1">No. de remolque:</label><div x-text="detalle.no_remolque"></div></div>
                            <div class="col-12 col-md-3 mb-3"><label class="form-label mb-1">Línea de transporte:</label><div x-text="detalle.linea_transporte"></div></div>
                        </div>

                        <!-- ===================================================== -->
                        <!-- TANQUES -->
                        <!-- ===================================================== -->

                        <h6 class="form-label mb-2">Tanques:</h6>
                        <div class="table-responsive mb-3">
                        <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                            <thead>

                                <tr>

                                    <th class="text-center">
                                        No. Tanque
                                    </th>

                                    <th class="text-end">
                                        Inventario Inicial
                                    </th>

                                    <th class="text-end">
                                        Inventario Final
                                    </th>

                                    <th class="text-center">
                                        Aditivación
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <template
                                    x-for="tanque in (detalle.tanques?.items || [])"
                                    :key="tanque.no_tanque">

                                    <tr>

                                        <td
                                            class="text-center"
                                            x-text="tanque.no_tanque">
                                        </td>

                                        <td
                                            class="text-end"
                                            x-text="tanque.inventario_inicial">
                                        </td>

                                        <td
                                            class="text-end"
                                            x-text="tanque.inventario_final">
                                        </td>

                                        <td
                                            class="text-center"
                                            x-text="tanque.aditivacion">
                                        </td>

                                    </tr>

                                </template>

                            </tbody>

                            <tfoot>

                                <tr>

                                    <td
                                        colspan="4"
                                        class="text-end">

                                        Merma:

                                        <span
                                            x-text="detalle.tanques?.merma || '0.00'">
                                        </span>

                                    </td>

                                </tr>

                            </tfoot>

                        </table>
                        </div>

                        <!-- ===================================================== -->
                        <!-- SELLOS -->
                        <!-- ===================================================== -->

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label mb-2">Sellos:</label>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered mb-0 align-middle">
                                        <thead><tr><th>Verificación</th><th class="text-center">Resultado</th></tr></thead>
                                        <tbody>
                                            <template x-for="sello in (detalle.sellos?.sellos || [])" :key="sello.verificar">
                                                <tr><td x-text="sello.verificar"></td><td class="text-center" x-text="sello.resultado"></td></tr>
                                            </template>
                                            <tr><td>No. Serie</td><td class="text-center" x-text="detalle.sello_noserie"></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-2">NICE:</label>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered mb-0 align-middle">
                                        <thead><tr><th>Verificación</th><th class="text-center">Resultado</th></tr></thead>
                                        <tbody>
                                            <template x-for="nice in (detalle.sellos?.nice || [])" :key="nice.verificar">
                                                <tr><td x-text="nice.verificar"></td><td class="text-center" x-text="nice.resultado"></td></tr>
                                            </template>
                                            <tr><td>Manómetro</td><td class="text-center" x-text="detalle.manometro"></td></tr>
                                            <tr><td>Temperatura</td><td class="text-center" x-text="detalle.temperatura"></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <label class="form-label mt-3 mb-1">Observaciones:</label>
                        <div class="p-3 bg-light rounded-2" x-text="detalle.observaciones || 'Sin observaciones'"></div>



                        <label class="form-label mt-4 mb-3">Firmas:</label>
                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <div class="card border h-100">
                                    <div class="card-header bg-primary text-white py-3 border-0">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                                                <i class="ti ti-user-check fs-5"></i>
                                            </div>
                                            <div class="ms-3"><h6 class="mb-0 text-white">Firma de quien recibe</h6></div>
                                        </div>
                                    </div>
                                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                                        <template x-if="detalle.firma_recibe?.firma">
                                            <img :src="detalle.firma_recibe.firma" class="img-fluid" style="max-height:90px;object-fit:contain;" alt="Firma de quien recibe">
                                        </template>
                                        <template x-if="!detalle.firma_recibe?.firma">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                                                <h6 class="text-muted mb-0">¡Falta la firma de quien recibe!</h6>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="card-footer bg-light text-center">
                                        <small class="text-muted" x-text="detalle.firma_recibe?.nombre || 'Pendiente de firma electrónica'"></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <div class="card border h-100">
                                    <div class="card-header bg-primary text-white py-3 border-0">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                                                <i class="ti ti-user-check fs-5"></i>
                                            </div>
                                            <div class="ms-3"><h6 class="mb-0 text-white">Firma de quien supervisa</h6></div>
                                        </div>
                                    </div>
                                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                                        <template x-if="detalle.firma_supervisa?.firma">
                                            <img :src="detalle.firma_supervisa.firma" class="img-fluid" style="max-height:90px;object-fit:contain;" alt="Firma de quien supervisa">
                                        </template>
                                        <template x-if="!detalle.firma_supervisa?.firma">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                                                <h6 class="text-muted mb-0">¡Falta la firma de quien supervisa!</h6>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="card-footer bg-light text-center">
                                        <small class="text-muted" x-text="detalle.firma_supervisa?.nombre || 'Pendiente de firma electrónica'"></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <div class="row g-0 ms-auto w-100 justify-content-end">
                            <div class="col-12 col-md-auto d-grid">
                                <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                                    <i class="ti ti-x"></i> Cerrar
                                </button>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    <?php endif; ?>

</div>
