<div id="container" class="mt-4 mb-5"
     x-data="permisosFormComponent()"
     data-modo="<?= $modo === 'editar' ? 'editar' : 'nuevo' ?>"
     data-id-usuario="<?= (int)($idUsuario ?? 0) ?>"
     data-id-estacion="<?= (int)($idEstacion ?? 0) ?>"
     data-estaciones='<?= htmlspecialchars(json_encode($estaciones ?? [], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     data-estaciones-cubre='<?= htmlspecialchars(json_encode($estacionesCubre ?? [], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     data-personal='<?= htmlspecialchars(json_encode($personal ?? [], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     data-detalle='<?= !empty($detalle) ? htmlspecialchars(json_encode($detalle, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') : '' ?>'>

    <div class="d-flex justify-content-end gap-2 mb-3">
        <button type="button" class="btn btn-success" @click="guardar()" :disabled="guardando">
            <template x-if="!guardando"><i class="ti ti-check fs-5 me-1"></i></template>
            <template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
            <span x-text="esNuevo ? 'Guardar' : 'Actualizar'"></span>
        </button>
    </div>

    <div class="row align-items-stretch">

        <div class="col-md-8 d-flex">
            <div class="card w-100">
                <div class="card-header bg-primary text-white py-3 border-0">
                    <h5 class="mb-0 text-white d-flex align-items-center gap-2">
                        <i class="ti ti-notes fs-5"></i>
                        <span >Datos del permiso</span>
                        <span x-show="!esNuevo" x-text="'(#' + (detalle ? detalle.id : '') + ')'"></span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">

                        <div class="col-md-12 mb-3">
                            <label class="form-label mb-1">* Colaborador:</label>
                            <select id="select-colaborador" class="form-select" x-model="colaborador">
                                <option value="">Selecciona una opción...</option>
                                <template x-for="p in personal" :key="p.id">
                                    <option :value="p.id" x-text="p.nombre"></option>
                                </template>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label mb-1">* Estación de quien cubre:</label>
                            <select class="form-select" x-model="estacionCubre" @change="onEstacionCubreChange($event)">
                                <option value="">Selecciona una opción...</option>
                                <template x-for="e in estacionesCubre" :key="e.id">
                                    <option :value="e.id" x-text="e.nombre"></option>
                                </template>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label mb-1">* Quien cubre el turno:</label>
                            <select id="select-cubre" class="form-select" x-model="cubre" disabled>
                                <option value="">Selecciona una opción...</option>
                            </select>
                            <div class="form-text" x-show="estacionCubre && cargandoCubre">Cargando personal...</div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label mb-1">* Del</label>
                            <input type="date" class="form-control" x-model="fechaInicio">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label mb-1">* Al</label>
                            <input type="date" class="form-control" x-model="fechaTermino">
                        </div>

                        <div class="col-md-4  mb-3">
                            <label class="form-label mb-1">Días:</label>
                            <input type="text" class="form-control" :value="diasCalculados" disabled>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">* Motivo</label>
                            <input type="text" class="form-control" x-model="motivo">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">Observaciones:</label>
                            <textarea class="form-control" rows="4" x-model="observaciones"></textarea>
                        </div>

                        <!-- 
                        <template x-if="!esNuevo && firmaExistente">
                            <div class="col-12">
                                <label class="form-label mb-1">Firma actual registrada</label>
                                <div class="border rounded-3 p-3 bg-light d-inline-block">
                                    <img :src="firmaExistente" alt="Firma actual" style="max-height:80px;object-fit:contain;">
                                </div>
                                <div class="form-text">Si deseas cambiarla, dibuja una nueva firma en el panel de la derecha.</div>
                            </div>
                        </template>

                        -->

                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 d-flex">
            <div class="card w-100">
                <div class="card-header text-bg-primary">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                        <h5 class="mb-0 text-white">
                            <i class="fa-solid fa-signature me-2"></i>
                            FIRMA DEL SOLICITANTE
                        </h5>
                        <button type="button" class="btn btn-danger btn-sm" @click="limpiarFirma()">
                            <i class="ti ti-eraser me-1"></i>
                            Limpiar firma
                        </button>
                    </div>
                </div>
                <div class="card-body p-0 d-flex">
                    <div id="signature-pad" class="signature-pad border-0 w-100 d-flex">
                        <div class="signature-pad--body w-100 d-flex">
                            <canvas id="firma-canvas-form"
                                style="width:100%; height:100%; min-height:0; display:block; cursor:crosshair; touch-action:none; background:#fff;"></canvas>
                        </div>
                        <input type="hidden" name="firma_solicitante" id="firma_solicitante" value="">
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>