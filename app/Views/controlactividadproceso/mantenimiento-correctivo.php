<div id="container" class="mb-4"
    x-data="{ ...actions(), ...mantenimientoCorrectivo()}"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

    <?php if (empty($estacionId)): ?>

        <div id="sasisopa-empty-message"
            class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
            Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
        </div>

    <?php else: ?>

        <div class="text-end mb-3">
            <div class="btn-group">
                <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical fs-4"></i>
                </button>
                <ul class="dropdown-menu animated rubberBand">
                    <li><a class="dropdown-item pointer" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>
                    <li>
                        <a class="dropdown-item pointer" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="datatables">
            <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
                <table id="table-mantenimiento-correctivo" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                    <thead>
                        <tr>
                            <th class="text-center align-middle">Folio</th>
                            <th class="text-center align-middle">Fecha</th>
                            <th class="text-center align-middle">Hora</th>
                            <th class="text-center align-middle">Nombre del equipo o área donde se detecta la no conformidad</th>
                            <th class="text-center align-middle">Descripción breve del hallazgo detectado que requiere mantenimiento</th>
                            <th class="text-center align-middle" width="48px">
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
                            @click="limpiarBuscar()">
                        </button>

                    </div>

                    <!-- BODY -->
                    <div class="modal-body">


                        <!-- YEAR -->

                        <label class="form-label mt-2">* Año:</label>
                        <select
                            class="form-select mb-3"
                            x-model="filtro.year"
                            :class="errorsBuscar.year ? 'is-invalid' : ''"
                            @input="errorsBuscar.year = false">

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
                            x-model="filtro.mes">

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
                            <i class="ti ti-x"></i> Cancelar
                        </button>
                    </div>
                     <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                       <button
                            class="btn btn-success"
                            @click="buscar()">
                            <i class="ti ti-search"></i></i> Buscar
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

                    <div class="modal-header modal-colored-header bg-primary text-white">

                        <h4 class="modal-title text-white">
                            <i class="ti ti-eye"></i>
                            Detalle de Mantenimiento Correctivo
                        </h4>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body">

                        <!-- INFORMACION -->
                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label class="form-label mb-1">
                                    Folio:
                                </label>

                                <div class="text-body text-break"
                                    x-text="detalle.folio">
                                </div>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label mb-1">
                                    Fecha:
                                </label>

                                <div class="text-body text-break"
                                    x-text="detalle.fechacreacion">
                                </div>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label mb-1">
                                    Hora:
                                </label>

                                <div class="text-body text-break"
                                    x-text="detalle.horacreacion">
                                </div>

                            </div>

                            <div class="col-12 mb-3">

                                <label class="form-label mb-1">
                                    Equipo o Área:
                                </label>

                                <div class="text-body text-break"
                                    x-text="detalle.nombre_equipo">
                                </div>

                            </div>

                            <div class="col-12 mb-3">

                                <label class="form-label mb-1">
                                    Hallazgo Detectado:
                                </label>

                                <div class="text-body text-break"
                                    x-text="detalle.descripcion_hallazgo">
                                </div>

                            </div>

                            <div class="col-12 mb-3">

                                <label class="form-label mb-1">
                                    Actividad Realizada:
                                </label>

                                <div class="text-body text-break"
                                    x-text="detalle.descripcion_actividad">
                                </div>

                            </div>

                            <div class="col-12 mb-4">

                                <label class="form-label mb-1">
                                    Herramienta Utilizada:
                                </label>

                                <div class="text-body text-break"
                                    x-text="detalle.herramienta">
                                </div>

                            </div>

                        </div>

                        <!-- TITULO EVIDENCIAS -->
                        <div class="d-flex align-items-center mt-1 mb-3">

                            <label class="form-label mb-1">
                                Evidencias
                            </label>

                            <span
                                class="badge bg-primary ms-2"
                                x-text="detalle.evidencias.length">
                            </span>

                        </div>

                        <!-- SIN EVIDENCIAS -->
                        <div
                            class="text-center py-5 border rounded"
                            x-show="detalle.evidencias.length <= 0">

                            <i class="ti ti-photo-off fs-7 text-muted"></i>

                            <div class="mt-2 text-muted">
                                No hay evidencias registradas
                            </div>

                        </div>

                        <!-- EVIDENCIAS -->
                        <div
                            class="row"
                            x-show="detalle.evidencias.length > 0">

                            <template
                                x-for="item in detalle.evidencias"
                                :key="item.id">

                                <div class="col-12 col-md-6 mb-3">
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
                                            <p class="small fw-bold text-truncate mb-0" :title="item.nombre" x-text="item.nombre || 'Evidencia de mantenimiento'"></p>
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

        <!-- MODAL EDITAR -->
        <div
            class="modal fade"
            id="ModalEditar"
            tabindex="-1"
            aria-hidden="true" 
            data-bs-backdrop="static"
            data-bs-keyboard="false">

            <div class="modal-dialog modal-lg modal-dialog-centered">

                <div class="modal-content">

                    <div class="modal-header modal-colored-header bg-primary text-white">

                        <h4 class="modal-title text-white">
                            <i class="ti ti-edit"></i>
                            Editar Mantenimiento Correctivo
                        </h4>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body">

                        <label class="form-label">
                            * Nombre del equipo:
                        </label>

                        <input
                            type="text"
                            class="form-control mb-3"
                            x-model="form.nombre_equipo">

                        <label class="form-label">
                            * Hallazgo detectado:
                        </label>

                        <textarea
                            class="form-control mb-3"
                            rows="3"
                            x-model="form.descripcion_hallazgo">
                </textarea>

                        <label class="form-label">
                            * Actividad realizada:
                        </label>

                        <textarea
                            class="form-control mb-3"
                            rows="3"
                            x-model="form.descripcion_actividad">
                </textarea>

                        <label class="form-label">
                            * Herramienta utilizada:
                        </label>

                        <textarea
                            class="form-control"
                            rows="4"
                            x-model="form.herramienta">
                </textarea>

                    </div>

                    <div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                       <button
                            class="btn bg-danger-subtle text-danger"
                            data-bs-dismiss="modal">

                            <i class="ti ti-x"></i> Cancelar
                        </button>
                    </div>
                     <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      
                        <button
                            class="btn btn-success"
                            @click="guardarEditar()">

                            <i class="ti ti-check"></i> Actualizar
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
                            <i class="ti ti-camera"></i>
                            Evidencias
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
                                            <p class="small fw-bold text-truncate mb-0" :title="item.nombre" x-text="item.nombre || 'Evidencia guardada'"></p>
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
