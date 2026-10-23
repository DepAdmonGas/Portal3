<div id="container" class="mt-4 mb-4" data-puede-administrar="<?= $puedeAdministrarSeguro ? 'true' : 'false' ?>">
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header text-bg-primary">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                        <h5 class="mb-0 text-white"><i class="ti ti-file-certificate fs-4 me-1"></i>Póliza de Seguro</h5>
                        <button type="button" class="btn btn-success seguro-admin-only d-none" data-bs-toggle="modal" data-bs-target="#modalPolizaSeguro">
                            <i class="ti ti-plus me-1"></i>Nuevo
                        </button>
                    </div>
                </div>
                <div class="card-body mb-0 seguro-card-body">
                    <div class="seguro-admin-only d-none">
                        <div class="datatables">
                            <div class="table-responsive overflow-x-auto overflow-hidden pb-4">
                                <table id="table-poliza" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                                    <thead><tr><th>#</th><th>Fecha y hora</th><th>Estatus</th><th><i class="ti ti-dots-vertical fs-6"></i></th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="seguro-solo-lectura d-none">
                        <iframe id="preview-poliza" class="border-0 d-none w-100" title="Vista previa de la póliza de seguro" style="height: min(70vh, 760px);"></iframe>
                        <div id="sin-poliza-vigente" class="flex-column justify-content-center align-items-center gap-2 p-4 text-center text-muted d-none" role="status" style="min-height: min(70vh, 760px);">
                            <i class="ti ti-file-off display-4" aria-hidden="true"></i><span class="empty-message">No se encontró un archivo vigente disponible.</span>
                        </div>
                    </div>
                </div>
                <div id="footer-download-poliza" class="card-footer seguro-solo-lectura d-none">
                    <div class="d-grid">
                        <a id="download-poliza" class="btn btn-success d-none" href="#"><i class="ti ti-download me-1"></i>Descargar póliza vigente</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header text-bg-primary">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                        <h5 class="mb-0 text-white"><i class="ti ti-shield-check fs-4 me-1"></i>Cobertura de la Póliza de Seguro</h5>
                        <button type="button" class="btn btn-success seguro-admin-only d-none" data-bs-toggle="modal" data-bs-target="#modalPolizaCobertura">
                            <i class="ti ti-plus me-1"></i>Nuevo
                        </button>
                    </div>
                </div>
                <div class="card-body seguro-card-body">
                    <div class="seguro-admin-only d-none">
                        <div class="datatables">
                            <div class="table-responsive overflow-x-auto overflow-hidden pb-4">
                                <table id="table-poliza-cobertura" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                                    <thead><tr><th>#</th><th>Fecha y hora</th><th>Estatus</th><th><i class="ti ti-dots-vertical fs-6"></i></th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="seguro-solo-lectura d-none">
                        <iframe id="preview-cobertura" class="border rounded d-none w-100" title="Vista previa de la cobertura de la póliza" style="height: min(70vh, 760px);"></iframe>
                        <div id="sin-cobertura-vigente" class="flex-column justify-content-center align-items-center gap-2 p-4 text-center text-muted d-none" role="status" style="min-height: min(70vh, 760px);">
                            <i class="ti ti-file-off display-4" aria-hidden="true"></i><span class="empty-message">No se encontró un archivo vigente disponible.</span>
                        </div>
                    </div>
                </div>
                <div id="footer-download-cobertura" class="card-footer seguro-solo-lectura d-none">
                    <div class="d-grid">
                        <a id="download-cobertura" class="btn btn-success d-none" href="#"><i class="ti ti-download me-1"></i>Descargar cobertura vigente</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPolizaSeguro" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="modalPolizaSeguroTitulo" aria-hidden="true" x-data="{ ...actions(), ...polizaForm() }">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white" id="modalPolizaSeguroTitulo"><i class="ti ti-file-certificate me-1"></i>Nueva póliza de seguro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" @click="resetForm()"></button>
                </div>
                <div class="modal-body">
                    <label for="seguro-poliza-archivo" class="form-label">* Póliza de seguro:</label>
                    <input id="seguro-poliza-archivo" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="form-control" x-ref="poliza" @change="handleFile($event)" :class="errors.poliza ? 'is-invalid' : ''">
                    <div class="invalid-feedback" x-show="errors.poliza">Selecciona un archivo PDF o imagen válido.</div>
                    <div class="form-text">PDF, JPG o PNG de hasta 10 MB. Las versiones anteriores se conservan.</div>
                </div>
                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn bg-danger-subtle text-danger me-0 me-md-2" data-bs-dismiss="modal" @click="resetForm()"><i class="ti ti-x me-1"></i>Cancelar</button>
                        </div>
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn btn-success mt-1 mt-md-0" @click="submit()" :disabled="loading">
                                <span class="spinner-border spinner-border-sm me-1" x-show="loading"></span><i class="ti ti-check me-1" x-show="!loading"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPolizaCobertura" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="modalPolizaCoberturaTitulo" aria-hidden="true" x-data="{ ...actions(), ...coberturaForm() }">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white" id="modalPolizaCoberturaTitulo"><i class="ti ti-shield-check me-1"></i>Nueva cobertura de póliza</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" @click="resetForm()"></button>
                </div>
                <div class="modal-body">
                    <label for="seguro-cobertura-archivo" class="form-label">* Cobertura de póliza de seguro:</label>
                    <input id="seguro-cobertura-archivo" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="form-control" x-ref="cobertura" @change="handleFile($event)" :class="errors.cobertura ? 'is-invalid' : ''">
                    <div class="invalid-feedback" x-show="errors.cobertura">Selecciona un archivo PDF o imagen válido.</div>
                    <div class="form-text">PDF, JPG o PNG de hasta 10 MB. Las versiones anteriores se conservan.</div>
                </div>
                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn bg-danger-subtle text-danger me-0 me-md-2" data-bs-dismiss="modal" @click="resetForm()"><i class="ti ti-x me-1"></i>Cancelar</button>
                        </div>
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn btn-success mt-1 mt-md-0" @click="submit()" :disabled="loading">
                                <span class="spinner-border spinner-border-sm me-1" x-show="loading"></span><i class="ti ti-check me-1" x-show="!loading"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-seguro-preview" tabindex="-1" aria-labelledby="modal-seguro-preview-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"><i class="ti ti-eye me-1"></i><span id="modal-seguro-preview-title">Documento de Seguro</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="seguro-preview-frame" class="border-0 d-block w-100" title="Vista previa del documento" style="height: min(75vh, 900px);"></iframe>
                </div>
                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn bg-danger-subtle text-danger me-0 me-md-2" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button>
                        </div>
                        <div class="col-12 col-md-auto d-grid">
                            <a id="seguro-preview-download" class="btn btn-success mt-1 mt-md-0" href="#"><i class="ti ti-download me-1"></i>Descargar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
