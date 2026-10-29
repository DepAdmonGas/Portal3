<div id="container" class="mb-4"
    x-data="{ ...actions(), ...mantenimientoPreventivo()}"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

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
                    <li><a class="dropdown-item pointer" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>
                    <li>
                        <a class="dropdown-item pointer" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item pointer" href="/sasisopa/control-actividades-procesos/detector-humo"><i class="ti ti-alarm-smoke"></i> Detector de Humo</a>
                    </li>

                    <li>
                        <a class="dropdown-item pointer" href="/sasisopa/control-actividades-procesos/extintores"><i class="ti ti-fire-extinguisher"></i> Extintores</a>
                    </li>

                    <li>
                        <a class="dropdown-item pointer" href="/sasisopa/control-actividades-procesos/mantenimiento-correctivo"><i class="ti ti-tool"></i> Mantenimiento Correctivo</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="datatables">
            <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
                <table id="table-mantenimiento-preventivo" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                    <thead>
                        <tr>
                            <th class="text-center align-middle">Folio</th>
                            <th class="text-start align-middle">Equipo o instalación</th>
                            <th class="text-center align-middle">Fecha</th>
                            <th class="text-center align-middle">Hora</th>
                            <th class="text-center align-middle">Estatdo</th>
                            <th class="text-center" width="48px">
                                <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
                            </th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- MODAL BUSCAR -->
        <div
            x-ref="ModalBuscar"
            class="modal fade"
            id="ModalBuscar"
            tabindex="-1"
            aria-hidden="true"
            data-bs-backdrop="static"
            data-bs-keyboard="false">

            <div class="modal-dialog modal-dialog-centered">

                <div class="modal-content border-0 shadow-lg">

                    <!-- HEADER -->
                    <div class="modal-header modal-colored-header bg-primary text-white">

                        <div>

                            <h4 class="modal-title text-white">
                                <i class="ti ti-search"></i>
                                Buscar registros
                            </h4>

                            <small class="">
                                Filtra la información del mantenimiento preventivo
                            </small>

                        </div>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                        </button>

                    </div>

                    <!-- BODY -->
                    <div class="modal-body">

                        <!-- EQUIPO -->

                        <label class="form-label fw-semibold">
                            Equipo o instalación:
                        </label>

                        <div
                            class="select2-modal-field mb-2"
                            x-ref="equipoWrapper">

                            <select
                                id="selectEquipo"
                                x-ref="selectEquipo"
                                data-width="100%">

                                <option value="">
                                    Todos
                                </option>

                                <template
                                    x-for="equipo in equipos"
                                    :key="equipo.id">

                                    <option
                                        :value="equipo.id"
                                        x-text="equipo.detalle">
                                    </option>

                                </template>

                            </select>

                        </div>

                        <label class="form-label fw-semibold">
                            Año:
                        </label>

                        <select
                            class="form-select mb-2"
                            x-model="filtro.year">

                            <option value="">
                                Todos
                            </option>

                            <template
                                x-for="year in years">

                                <option
                                    :value="year"
                                    x-text="year">
                                </option>

                            </template>

                        </select>

                        <label class="form-label fw-semibold">
                            Mes:
                        </label>

                        <select
                            class="form-select mb-2"
                            x-model="filtro.mes"
                            :class="errors.mes ? 'is-invalid' : ''"
                            @input="errors.mes = false">

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
                            @click="limpiarBuscar()">
                            <i class="ti ti-x"></i>
                            Cancelar

                        </button>

                    </div>
                    <div class="col-12 col-md-auto d-grid ms-0 ms-2">
                      
                        <button
                            class="btn btn-success"
                            @click="buscar()">

                            <i class="ti ti-search me-1"></i>
                            Buscar

                        </button>
                    </div>
                  </div>

                      

                    </div>

                </div>

            </div>

        </div>

        <!-- MODAL DETALLE -->
        <div
            class="modal fade"
            id="ModalDetalle"
            tabindex="-1"
            aria-hidden="true"
            data-bs-backdrop="static"
            data-bs-keyboard="false">

            <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">

                <div class="modal-content">

                    <div class="modal-header bg-primary text-white">

                        <h4 class="modal-title text-white">
                            <i class="ti ti-file-settings"></i> Detalle de Mantenimiento Preventivo
                        </h4>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label class="form-label mb-1">
                                    Folio:
                                </label>

                                <div
                                    x-text="detalle.folio">
                                </div>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label mb-1">
                                    Fecha:
                                </label>

                                <div
                                    x-text="detalle.fechacreacion">
                                </div>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Hora:
                                </label>

                                <div
                                    x-text="detalle.horacreacion">
                                </div>

                            </div>

                            <div class="col-md-12 mb-3">

                                <label class="form-label mb-1">
                                    Equipo:
                                </label>

                                <div
                                    x-text="detalle.nombre_equipo">
                                </div>

                            </div>

                        </div>

                        <div class="d-flex align-items-center mt-1 mb-3">

                            <label class="form-label mb-1">
                                Evidencias:
                            </label>

                            <span
                                class="badge bg-primary ms-2"
                                x-text="detalle.evidencias.length">
                            </span>

                        </div>

                        <div
                            class="text-center py-5 border rounded"
                            x-show="detalle.evidencias.length <= 0">

                            <i class="ti ti-photo-off fs-7 text-muted"></i>

                            <div class="mt-2 text-muted">
                                No hay evidencias registradas
                            </div>

                        </div>

                        <div
                            class="row"
                            x-show="detalle.evidencias.length > 0">

                            <template
                                x-for="item in detalle.evidencias"
                                :key="item.id">

                                <div class="col-12 col-md-4 mb-3">
                                    <div class="card h-100 shadow-sm border bg-light position-relative overflow-hidden">
                                        <div class="position-absolute top-0 end-0 p-2" style="z-index:2;">
                                            <span class="badge bg-white text-dark rounded-circle shadow-sm d-flex align-items-center justify-content-center p-2" style="width:32px;height:32px;">
                                                <i class="ti ti-photo text-primary fs-6"></i>
                                            </span>
                                        </div>
                                        <a :href="item.url" target="_blank" class="d-block overflow-hidden" style="height:140px;">
                                            <img :src="item.url" class="w-100 h-100" style="object-fit:cover;" :alt="item.nombre || 'Evidencia de mantenimiento'">
                                        </a>
                                        <div class="card-body border-top pb-2 bg-white">
                                            <p class="small fw-bold text-truncate mb-0" :title="item.nombre" x-text="item.nombre"></p>
                                        </div>
                                        <div class="card-footer bg-white">
                                            <a class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-1" :href="item.url" :download="item.nombre" title="Descargar evidencia">
                                                <i class="ti ti-download"></i><span>Descargar</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            </template>

                        </div>


                    </div>

                    <div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid">
                       <button
                            class="btn bg-danger-subtle text-danger"
                            data-bs-dismiss="modal">

                            <i class="ti ti-x"></i> Cerrar
                        </button>
                    </div>
                  </div>

                       

                    </div>

                </div>

            </div>

        </div>

        <!-- MODAL EVIDENCIAS -->
        <div
            class="modal fade"
            id="ModalEvidencia"
            tabindex="-1"
            aria-hidden="true"
            data-bs-backdrop="static"
            data-bs-keyboard="false">

            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

                <div class="modal-content">

                    <div class="modal-header modal-colored-header bg-primary text-white">

                        <h4 class="modal-title text-white">
                            <i class="ti ti-camera"></i> Evidencias
                        </h4>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body">
                        <div class="col-12">
                            <label class="form-label mb-1">Imágenes:</label>
                            <div class="input-group">
                                <input
                                    type="file"
                                    class="form-control"
                                    accept="image/*"
                                    multiple
                                    @change="handleFiles($event)">
                                <button type="button" class="btn btn-success" @click="guardarEvidencias()">
                                    <i class="ti ti-check me-1"></i> Guardar
                                </button>
                            </div>
                        </div>

                        <!-- PREVIEW -->
                        <div
                            class="row"
                            x-show="previewImages.length > 0">

                            <template
                                x-for="(img,index) in previewImages"
                                :key="index">

                                <div class="col-12 col-md-6 mt-4">
                                    <div class="card h-100 shadow-sm border bg-light position-relative overflow-hidden">
                                        <span class="badge bg-warning text-dark position-absolute top-0 start-0 m-2" style="z-index:2;">Nueva</span>
                                        <div class="position-absolute top-0 end-0 p-2" style="z-index:2;">
                                            <span class="badge bg-white text-dark rounded-circle shadow-sm d-flex align-items-center justify-content-center p-2" style="width:32px;height:32px;">
                                                <i class="ti ti-photo text-primary fs-6"></i>
                                            </span>
                                        </div>
                                        <img :src="img" class="w-100" style="height:140px;object-fit:cover;" alt="Vista previa de evidencia">
                                        <div class="card-body border-top pb-2 bg-white">
                                            <p class="small fw-bold text-truncate mb-0" :title="files[index]?.name" x-text="files[index]?.name || 'Imagen nueva'"></p>
                                        </div>
                                        <div class="card-footer bg-white">
                                            <button type="button" class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-1" @click="removePreview(index)">
                                                <i class="ti ti-trash"></i><span>Eliminar</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </template>

                        </div>

                        <!-- EVIDENCIAS -->
                        <div class="row">

                            <template
                                x-for="(item,index) in evidencias"
                                :key="item.id">

                                <div class="col-12 col-md-6 mt-4">
                                    <div class="card h-100 shadow-sm border bg-light position-relative overflow-hidden">
                                        <div class="position-absolute top-0 end-0 p-2" style="z-index:2;">
                                            <span class="badge bg-white text-dark rounded-circle shadow-sm d-flex align-items-center justify-content-center p-2" style="width:32px;height:32px;">
                                                <i class="ti ti-photo text-primary fs-6"></i>
                                            </span>
                                        </div>
                                        <a :href="item.url" target="_blank" class="d-block overflow-hidden" style="height:140px;">
                                            <img :src="item.url" class="w-100 h-100" style="object-fit:cover;" :alt="item.nombre || 'Evidencia guardada'">
                                        </a>
                                        <div class="card-body border-top pb-2 bg-white">
                                            <p class="small fw-bold text-truncate mb-0" :title="item.nombre" x-text="item.nombre"></p>
                                        </div>
                                        <div class="card-footer bg-white">
                                            <div class="d-flex align-items-center gap-2">
                                                <a class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-1" :href="item.url" :download="item.nombre" title="Descargar">
                                                    <i class="ti ti-download"></i><span>Descargar</span>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-1" @click="eliminarEvidencia(item.id,index)" title="Eliminar">
                                                    <i class="ti ti-trash"></i><span>Eliminar</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </template>

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
