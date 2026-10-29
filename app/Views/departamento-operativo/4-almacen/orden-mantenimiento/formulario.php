<?php $baseUrl = '/departamento-operativo/almacen/orden-mantenimiento'; ?>
<div id="orden-mantenimiento-app"
     class="pb-4"
     data-base-url="<?= $baseUrl ?>"
     data-module-key="<?= htmlspecialchars((string)$moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
     data-record-id="<?= (int)($registro['id'] ?? 0) ?>"
     data-record="<?= htmlspecialchars(json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null', ENT_QUOTES, 'UTF-8') ?>"
     data-puede-leer="<?= !empty($permisos['leer']) ? 'true' : 'false' ?>"
     data-puede-crear="<?= !empty($permisos['crear']) ? 'true' : 'false' ?>"
     data-puede-editar="<?= !empty($permisos['editar']) ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= !empty($permisos['eliminar']) ? 'true' : 'false' ?>"
     x-data="{ ...actions() }">

    <section id="orden-formulario" class="row mt-3">
        <div class="col-12">
            <div class="d-flex justify-content-end mb-3">
                <button id="orden-finalizar" class="btn btn-success" type="button"><i class="ti ti-check me-1"></i> Finalizar</button>
            </div>
            <div class="card mb-3">
                <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-clipboard-text me-1"></i> Datos generales</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4"><label class="form-label mb-1">Razón social:</label><div id="orden-razon-social" class="text-body"></div></div>
                        <div class="col-12 col-md-4"><label class="form-label mb-1">Folio:</label><div id="orden-folio" class="text-body"></div></div>
                        <div class="col-12 col-md-4"><label class="form-label mb-1">Fecha:</label><div id="orden-fecha" class="text-body"></div></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-align-box-left-top me-1"></i> ¿En qué afecta a la estación?</h5></div>
                <div class="card-body p-0">
                    <textarea id="orden-afectacion" class="form-control p-3 border-0" rows="6" aria-label="¿En qué afecta a la estación?" placeholder="Ingresa aqui tu afectación..."></textarea>
                </div>
            </div>

<div class="row g-3 mt-3 mb-3">
    <!-- Card 1 -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-primary">
                <h5 class="mb-0 text-white d-flex align-items-center"><i class="ti ti-tool me-2"></i><span>Tipo de servicio</span></h5>
            </div>
            <!-- Agregamos d-flex flex-column aquí -->
            <div class="card-body p-0 d-flex flex-column">
                <div class="table-responsive flex-grow-1 d-flex">
                    <table class="table table-bordered mb-0 text-center align-middle w-100" data-portal-table-selection="false">
                        <thead>
                            <tr><th class="text-center align-middle">Preventivo</th><th class="text-center align-middle">Correctivo</th><th class="text-center align-middle">Emergente</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center align-middle"><input id="orden-tipo-preventivo" class="form-check-input js-orden-choice m-0" type="radio" name="orden-tipo-radio" value="1" aria-label="Preventivo"></td>
                                <td class="text-center align-middle"><input id="orden-tipo-correctivo" class="form-check-input js-orden-choice m-0" type="radio" name="orden-tipo-radio" value="2" aria-label="Correctivo"></td>
                                <td class="text-center align-middle"><input id="orden-tipo-emergente" class="form-check-input js-orden-choice m-0" type="radio" name="orden-tipo-radio" value="3" aria-label="Emergente"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <select id="orden-tipo" class="visually-hidden" tabindex="-1" aria-hidden="true">
                    <option value="0">Selecciona una opción...</option>
                    <option value="1">Preventivo</option>
                    <option value="2">Correctivo</option>
                    <option value="3">Emergente</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Card 2 -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-primary">
                <h5 class="mb-0 text-white d-flex align-items-center"><i class="ti ti-building me-2"></i><span>¿La orden de trabajo se puede atender internamente?</span></h5>
            </div>
            <div class="card-body p-0 d-flex flex-column">
                <div class="table-responsive flex-grow-1 d-flex">
                    <table class="table table-bordered mb-0 text-center align-middle w-100" data-portal-table-selection="false">
                        <thead>
                            <tr><th class="text-center align-middle">Sí</th><th class="text-center align-middle">No</th><th class="text-center align-middle">Ambas</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center align-middle"><input id="orden-trabajo-si" class="form-check-input js-orden-choice m-0" type="radio" name="orden-trabajo-radio" value="1" aria-label="Sí"></td>
                                <td class="text-center align-middle"><input id="orden-trabajo-no" class="form-check-input js-orden-choice m-0" type="radio" name="orden-trabajo-radio" value="2" aria-label="No"></td>
                                <td class="text-center align-middle"><input id="orden-trabajo-ambas" class="form-check-input js-orden-choice m-0" type="radio" name="orden-trabajo-radio" value="3" aria-label="Ambas"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <select id="orden-trabajo" class="visually-hidden" tabindex="-1" aria-hidden="true">
                    <option value="0">Selecciona una opción...</option>
                    <option value="1">Sí</option>
                    <option value="2">No</option>
                    <option value="3">Ambas</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Card 3 -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header bg-primary">
                <h5 class="mb-0 text-white d-flex align-items-center"><i class="ti ti-alert-triangle me-2"></i><span>¿La orden de trabajo es de alto riesgo?</span></h5>
            </div>
            <div class="card-body p-0 d-flex flex-column">
                <div class="table-responsive flex-grow-1 d-flex">
                    <table class="table table-bordered mb-0 text-center align-middle w-100" data-portal-table-selection="false">
                        <thead>
                            <tr><th class="text-center align-middle">Sí</th><th class="text-center align-middle">No</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center align-middle"><input id="orden-riesgo-si" class="form-check-input js-orden-choice m-0" type="radio" name="orden-riesgo-radio" value="1" aria-label="Sí"></td>
                                <td class="text-center align-middle"><input id="orden-riesgo-no" class="form-check-input js-orden-choice m-0" type="radio" name="orden-riesgo-radio" value="2" aria-label="No"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <select id="orden-riesgo" class="visually-hidden" tabindex="-1" aria-hidden="true">
                    <option value="0">Selecciona una opción...</option>
                    <option value="1">Sí</option>
                    <option value="2">No</option>
                </select>
            </div>
        </div>
    </div>

</div>

            <div class="card mt-3"><div class="card-header bg-primary d-flex align-items-center justify-content-between"><h5 class="mb-0 text-white"><i class="ti ti-map-pin me-1"></i> Áreas</h5><button id="orden-agregar-area" type="button" class="btn btn-success"><i class="ti ti-plus me-1"></i> Nueva área</button></div><div class="card-body p-0"><div id="orden-areas" class="table-responsive"></div></div></div>
            <div class="card mt-3"><div class="card-header bg-primary d-flex align-items-center justify-content-between"><h5 class="mb-0 text-white"><i class="ti ti-tool me-1"></i> Refacciones</h5><button id="orden-agregar-refaccion" class="btn btn-success" type="button"><i class="ti ti-plus me-1"></i> Nueva refacción</button></div><div class="card-body p-0"><div id="orden-materiales" class="table-responsive"></div></div></div>
            <div class="card mt-3" id="orden-evidencias"><div class="card-header bg-primary d-flex align-items-center justify-content-between"><h5 class="mb-0 text-white"><i class="ti ti-camera me-1"></i> Evidencia</h5><button id="orden-agregar-evidencia" type="button" class="btn btn-success"><i class="ti ti-plus me-1"></i> Nueva evidencia</button></div><div class="card-body p-0">
                <div id="orden-subir-instalacion" class="row g-2 align-items-end p-3 mb-0" hidden><div class="col-12 col-md-9"><label class="form-label mb-1">Archivo:</label><input id="orden-archivo-instalacion" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp"></div><div class="col-12 col-md-3"><button id="orden-guardar-instalacion" type="button" class="btn btn-primary w-100"><i class="ti ti-upload me-1"></i> Subir</button></div></div>
                <div id="orden-lista-instalacion" class="px-3" hidden></div><div id="orden-lista-evidencias" class="table-responsive"></div>
            </div></div>
            <div class="row g-3 mt-1 align-items-stretch">
                <div class="col-12 col-lg-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-eye me-1"></i> OBSERVACIONES</h5></div>
                        <div class="card-body p-0 flex-grow-1"><textarea id="orden-comentarios" class="form-control p-3 border-0 d-block w-100 h-100 m-0" rows="5" placeholder="Escribe aquí tu comentario..."></textarea></div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="card h-100" id="orden-firma-card">
                        <div class="card-header bg-primary"><h5 class="mb-0 text-white d-flex align-items-center"><span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center me-2" style="width:36px;height:36px"><i class="ti ti-signature"></i></span><span>Firma del encargado</span></h5></div>
                        <div class="card-body p-3 pb-0 flex-grow-1">
                            <div id="orden-firma-pad" class="signature-pad-wrapper border border-2 rounded" style="border-style:dashed!important;border-color:#adb5bd!important;cursor:crosshair">
                                <canvas id="orden-canvas-firma" class="w-100 d-block" style="height:180px;touch-action:none"></canvas>
                            </div>
                        </div>
                        <button id="orden-limpiar-firma" type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0 flex-shrink-0"><i class="ti ti-eraser me-1"></i> Limpiar firma</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/_modales.php'; ?>

</div>
