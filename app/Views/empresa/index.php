<div id="container" class="mt-4 mb-4" data-puede-administrar="<?= $puedeAdministrarEmpresa ? 'true' : 'false' ?>" x-data="{ ...actions(), ...empresaAdminComponent() }">
   
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-end gap-2 mb-3">
    <button type="button" class="btn bg-primary-subtle text-primary btn-empresa-nuevo d-none" @click="abrirNuevo()">
        <i class="ti ti-plus me-1"></i>Nuevo
    </button>
</div>

<div class="datatables">
    <div class="table-responsive overflow-x-auto overflow-hidden">
        <table id="table-empresa" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descripción</th>
                    <th>Tipo</th>
                    <th><i class="ti ti-dots-vertical fs-6"></i></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

    <div class="modal fade" id="modal-empresa-form" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="modal-empresa-form-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white" id="modal-empresa-form-title">
                        <i class="ti ti-building-community me-1"></i>
                        <span x-text="modoEmpresa === 'crear' ? 'Nuevo registro' : 'Editar registro'"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="form-empresa" x-ref="formEmpresa" enctype="multipart/form-data" novalidate @submit.prevent="guardarEmpresa()">
                    <div class="modal-body">
                        <input type="hidden" name="id" x-ref="empresaId">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="empresa-descripcion" class="form-label">* Descripción:</label>
                                <textarea class="form-control" id="empresa-descripcion" name="descripcion" rows="2" maxlength="2000" required
                                    :class="erroresEmpresa.descripcion ? 'is-invalid' : ''" x-ref="empresaDescripcion"
                                    @input="erroresEmpresa.descripcion = ''"></textarea>
                                <div class="invalid-feedback" x-show="erroresEmpresa.descripcion" x-text="erroresEmpresa.descripcion"></div>
                            </div>
                            <div class="col-12">
                                <label for="empresa-tipo" class="form-label">* Tipo:</label>
                                <select class="form-select" id="empresa-tipo" name="id_tipo" required
                                    :class="erroresEmpresa.id_tipo ? 'is-invalid' : ''" x-ref="empresaTipo"
                                    @change="erroresEmpresa.id_tipo = ''">
                                    <option value="">Selecciona un tipo...</option>
                                    <template x-for="tipo in tiposEmpresa" :key="tipo.id">
                                        <option :value="tipo.id" x-text="tipo.tipo"></option>
                                    </template>
                                </select>
                                <div class="invalid-feedback" x-show="erroresEmpresa.id_tipo" x-text="erroresEmpresa.id_tipo"></div>
                            </div>
                            <div class="col-12">
                                <label for="empresa-archivo" class="form-label" x-text="modoEmpresa === 'crear' ? '* Archivo PDF:' : 'Archivo PDF (opcional):'"></label>
                                <input class="form-control" type="file" id="empresa-archivo" name="archivo" accept="application/pdf,.pdf"
                                    :required="modoEmpresa === 'crear'" :class="erroresEmpresa.archivo ? 'is-invalid' : ''"
                                    x-ref="empresaArchivo" @change="erroresEmpresa.archivo = ''">
                                <div class="invalid-feedback" x-show="erroresEmpresa.archivo" x-text="erroresEmpresa.archivo"></div>
                                <div class="form-text">PDF de hasta 25 MB. Los archivos previos se conservan como históricos.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="row g-0 ms-auto w-100 justify-content-end">
                            <div class="col-12 col-md-auto d-grid">
                                <button type="button" class="btn bg-danger-subtle text-danger me-0 me-md-2" data-bs-dismiss="modal">
                                    <i class="ti ti-x me-1"></i>Cancelar
                                </button>
                            </div>
                            <div class="col-12 col-md-auto d-grid">
                                <button type="submit" class="btn btn-success mt-1 mt-md-0" :disabled="loading">
                                    <span class="spinner-border spinner-border-sm me-1" x-show="loading"></span>
                                    <i class="ti ti-check me-1" x-show="!loading"></i>Guardar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-empresa-preview" tabindex="-1" aria-labelledby="modal-empresa-preview-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white" id="modal-empresa-preview-title">Documento de Empresa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="empresa-preview-frame" class="border-0 d-block w-100" title="Vista previa del documento" style="height: min(75vh, 900px);"></iframe>
                </div>
                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn bg-danger-subtle text-danger me-0 me-md-2" data-bs-dismiss="modal">
                                <i class="ti ti-x me-1"></i>Cerrar
                            </button>
                        </div>
                        <div class="col-12 col-md-auto d-grid">
                            <a id="empresa-preview-download" class="btn btn-success mt-1 mt-md-0" href="#">
                                <i class="ti ti-download me-1"></i>Descargar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
