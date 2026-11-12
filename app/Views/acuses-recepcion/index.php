<!-- Acuses de Recepción -->
<div id="acuses-recepcion"
     class="mt-3 mb-4"
     x-data="{ ...actions(), ...acusesRecepcionComponent() }"
     data-puede-agregar="<?= $permisos['puede_agregar'] ? 'true' : 'false' ?>"
     data-puede-editar="<?= $permisos['puede_editar'] ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $permisos['puede_eliminar'] ? 'true' : 'false' ?>"
     data-es-admin="<?= $permisos['es_admin'] ? 'true' : 'false' ?>"
     data-empresas="<?= htmlspecialchars(json_encode($empresas, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">

    <div class="row g-3">
 


        <div class="col-12 d-flex justify-content-end gap-2">

<div class="dropdown">
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>
<ul class="dropdown-menu dropdown-menu-end">
<li>
<a class="dropdown-item pointer" x-show="puedeAgregar" @click="agregar()" :disabled="guardando">
<i class="ti ti-plus"></i> Nuevo
</a>
</li>

<li>
<a class="dropdown-item pointer" x-show="esAdmin" @click="abrirBuscar()">
<i class="ti ti-search"></i> Buscar
</a>
</li>

</ul>
</div>

</div>

<div class="table-responsive pb-5">
<table id="tabla-acuses-recepcion" class="table table-striped table-bordered mb-0 align-middle"></table>
</div>
</div>

    <!-- Modal: gestión del acuse (agregar documentos / finalizar) -->
    <div class="modal fade" id="modalAcuse" tabindex="-1"
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <i class="ti ti-file-description me-2"></i>
                    <h5 class="modal-title text-white" x-text="tituloGestion">Formulario Acuses de Recepción</h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="row" x-show="acuse">

                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">Fecha:</label>
                            <div x-text="acuse ? acuse.fecha_creacion_fmt : ''"></div>
                        </div>

                        <div class="col-12 mb-3" x-show="puedeEditar">
                            <label class="form-label mb-1">Empresa:</label>
                            <input type="text" class="form-control"
                                   list="listaEmpresas"
                                   x-model="form.empresa"
                                   :disabled="bloqueado">
                            <datalist id="listaEmpresas">
                                <template x-for="emp in empresas" :key="emp">
                                    <option :value="emp"></option>
                                </template>
                            </datalist>
                        </div>

                        <div class="col-12 mb-2" x-show="!puedeEditar">
                            <label class="form-label mb-1">Empresa:</label>
                            <div x-text="acuse ? (acuse.empresa || 'Sin empresa') : ''"></div>
                        </div>

                        <div class="col-12">
                                    <h5 class="text-primary">Documentos a entregar</h5>
                        </div>

<!-- Se evalúa la condición una sola vez para todo el bloque -->
<template x-if="puedeEditar && !bloqueado">
    <div class="row align-items-end mb-3">
        
        <!-- Nombre del documento -->
        <div class="col-xl-5 col-lg-5 col-md-5 col-12 mb-2 mb-xl-0">
            <label class="form-label mb-1">* Nombre del documento:</label>
            <textarea class="form-control" rows="1"
                      :class="{ 'is-invalid': errores.documento }"
                      x-model="form.documento"></textarea>
        </div>

        <!-- No. de páginas -->
        <div class="col-xl-3 col-lg-3 col-md-3 col-12 mb-2 mb-xl-0">
            <label class="form-label mb-1">* No. de páginas:</label>
            <input type="number" min="1" class="form-control"
                   :class="{ 'is-invalid': errores.paginas }"
                   x-model="form.paginas">
        </div>

        <!-- Checkboxes: Original y Copia -->
        <div class="col-xl-1 col-lg-2 col-md-2 col-6 text-center mb-2 mb-xl-0">
            <label class="form-label d-block mb-1">Original</label>
            <div class="py-1">
                <input type="checkbox" class="form-check-input" x-model="form.original">
            </div>
        </div>

        <div class="col-xl-1 col-lg-2 col-md-2 col-6 text-center mb-2 mb-xl-0">
            <label class="form-label d-block mb-1">Copia</label>
            <div class="py-1">
                <input type="checkbox" class="form-check-input" x-model="form.copia">
            </div>
        </div>

        <!-- Botón de Nuevo documento integrado en la misma línea -->
        <div class="col-xl-2 col-lg-12 col-md-12 col-12 text-xl-end text-center">
            <button type="button"
                    class="btn bg-primary-subtle text-primary w-100 w-xl-auto"
                    @click="agregarDocumento()"
                    :disabled="guardando"
                    title="Nuevo documento">
                <i class="ti ti-plus"></i>
                <span>Nuevo</span> <!-- Texto visible en móvil, icono solo en desktop si falta espacio -->
            </button>
        </div>

    </div>
</template>


                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" width="48px">#</th>
                                            <th class="text-start">Nombre del documento</th>
                                            <th class="text-center">Número de páginas</th>
                                            <th class="text-center" width="96px">Original</th>
                                            <th class="text-center" width="96px">Copia</th>
                                            <th class="text-center" width="48" x-show="puedeEditar && !bloqueado">
                                                <i class="ti ti-trash fs-6 text-danger"></i>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(doc, index) in documentos" :key="doc.id">
                                            <tr>
                                                <td class="text-center" x-text="index + 1"></td>
                                                <td class="text-start" x-text="doc.documento"></td>
                                                <td class="text-center" x-text="doc.paginas"></td>
                                                <td class="text-center">
                                                    <strong x-show="doc.original">SI</strong>
                                                    <span x-show="!doc.original">NO</span>
                                                </td>
                                                <td class="text-center">
                                                    <strong x-show="doc.copia">SI</strong>
                                                    <span x-show="!doc.copia">NO</span>
                                                </td>
                                                <td class="text-center" x-show="puedeEditar && !bloqueado">
                                                    <a class="pointer" @click="eliminarDocumento(doc.id, doc.documento)">
                                                        <i class="ti ti-trash fs-6 text-danger"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        </template>

                                        <template x-if="documentos.length === 0">
                                            <tr>
                                                <td colspan="6" class="text-center text-primary">
                                                    No se encontro información
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="text-center py-4" x-show="cargandoDetalle">
                        <span class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>

                <div class="modal-footer">

             <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cerrar
                    </button>


                    <template x-if="puedeEditar && acuse && !bloqueado">
                        <button type="button" class="btn btn-danger"
                                @click="eliminar()"
                                :disabled="guardando">
                            <i class="ti ti-trash"></i> Eliminar
                        </button>
                    </template>

                    <template x-if="puedeEditar && acuse && !bloqueado">
                        <button type="button" class="btn btn-success"
                                @click="pasarAEntrega()"
                                :disabled="guardando || documentos.length === 0">
                            <i class="ti ti-check"></i> Finalizar
                        </button>
                    </template>

                </div>
            </div>
        </div>
    </div>

    <!-- Modal: finalizar (quién recibe) -->
    <div class="modal fade" id="modalFinalizarAcuse" tabindex="-1"
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <i class="ti ti-package-export me-2"></i>
                    <h5 class="modal-title text-white">Entrega de Acuse de Recepción</h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3" x-show="acuse">
                        <label class="form-label mb-1">Empresa:</label>
                        <h6 x-text="acuse ? (acuse.empresa || 'Sin empresa') : ''"></h6>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">* Quién recibe:</label>
                        <textarea class="form-control" rows="1"
                                  :class="{ 'is-invalid': errores.quien_recibe }"
                                  x-model="form.quien_recibe"></textarea>
                    </div>

                    <div class="table-responsive" x-show="acuse">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" width="48px">#</th>
                                    <th class="text-start">Nombre del documento</th>
                                    <th class="text-center">Número de páginas</th>
                                    <th class="text-center" width="96px">Original</th>
                                    <th class="text-center" width="96px">Copia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(doc, index) in documentos" :key="doc.id">
                                    <tr>
                                        <td class="text-center" x-text="index + 1"></td>
                                        <td class="text-start" x-text="doc.documento"></td>
                                        <td class="text-center" x-text="doc.paginas"></td>
                                        <td class="text-center">
                                            <strong x-show="doc.original">SI</strong>
                                            <span x-show="!doc.original">NO</span>
                                        </td>
                                        <td class="text-center">
                                            <strong x-show="doc.copia">SI</strong>
                                            <span x-show="!doc.copia">NO</span>
                                        </td>
                                    </tr>
                                </template>

                                <template x-if="documentos.length === 0">
                                    <tr>
                                        <td colspan="5" class="text-center text-primary">
                                            No se encontro información
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-center py-4" x-show="cargandoDetalle">
                        <span class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success"
                            @click="confirmarEntrega()"
                            :disabled="guardando">
                        <i class="ti ti-check"></i> Entrega
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: buscar acuse -->
    <div class="modal fade" id="modalBuscarAcuse" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <i class="ti ti-search me-2"></i>
                    <h5 class="modal-title text-white">Buscar Acuses de Recepción</h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <label class="form-label mb-1">Estatus de acuse:</label>
                    <select class="form-select" x-model="filtro">
                        <option value="0">Todas</option>
                        <option value="3">Pendiente</option>
                        <option value="1">En Proceso</option>
                        <option value="2">Finalizadas</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" @click="buscar()">
                        <i class="ti ti-search"></i> Buscar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: detalle del acuse de recepción -->
    <div class="modal fade" id="modalDetalleAcuse" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h4 class="modal-title text-white d-flex align-items-center gap-2">
                        <i class="ti ti-eye"></i>
                        <span x-text="acuse ? 'Acuse de Recepción (' + acuse.fecha_creacion_fmt + ')' : 'Acuse de Recepción'"></span>
                    </h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="row" x-show="acuse">
                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">Estatus:</label>
                            <div>
                                <span class="badge"
                                      :class="'bg-' + (acuse ? acuse.estado_color : 'secondary')"
                                      x-text="acuse ? acuse.estado_texto : ''"></span>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">Empresa:</label>
                            <h6 x-text="acuse ? (acuse.empresa || 'Sin empresa') : ''"></h6>
                        </div>

                        <div class="col-xl-4 col-lg-4 col-md-12 col-12 mb-3">
                            <label class="form-label mb-1">Quién entrega:</label>
                            <h6 x-text="acuse && acuse.entrega ? acuse.entrega : 'Sin información'"></h6>
                        </div>

                        <div class="col-xl-4 col-lg-4 col-md-12 col-12 mb-3">
                            <label class="form-label mb-1">Quién recibió:</label>
                            <h6 x-text="acuse && acuse.nombre_recibe ? acuse.nombre_recibe : 'Sin información'"></h6>
                        </div>

                        <div class="col-xl-4 col-lg-4 col-md-12 col-12 mb-3">
                            <label class="form-label mb-1">Fecha y hora:</label>
                            <h6 x-text="acuse && acuse.fecha_entrega_fmt ? acuse.fecha_entrega_fmt : 'Sin información'"></h6>
                        </div>

            <div class="col-12">
                                    <h5 class="text-primary">Documentos a entregar</h5>
                        </div>

                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" width="60">#</th>
                                            <th class="text-start">Nombre del documento</th>
                                            <th class="text-center">Número de páginas</th>
                                            <th class="text-center">Original</th>
                                            <th class="text-center">Copia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(doc, index) in documentos" :key="doc.id">
                                            <tr>
                                                <td class="text-center" x-text="index + 1"></td>
                                                <td class="text-start" x-text="doc.documento"></td>
                                                <td class="text-center" x-text="doc.paginas"></td>
                                                <td class="text-center">
                                                    <strong x-show="doc.original">SI</strong>
                                                    <span x-show="!doc.original">NO</span>
                                                </td>
                                                <td class="text-center">
                                                    <strong x-show="doc.copia">SI</strong>
                                                    <span x-show="!doc.copia">NO</span>
                                                </td>
                                            </tr>
                                        </template>

                                        <template x-if="documentos.length === 0">
                                            <tr>
                                                <td colspan="5" class="text-center text-primary">
                                                    No se encontro información
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="text-center py-4" x-show="cargandoDetalle">
                        <span class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
