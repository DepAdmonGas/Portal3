<div
    id="container"
    class="pb-4"
    x-data="{ ...actions(), ...estaciones() }">

    <div class="text-end">

        <button
            type="button"
            class="btn btn-primary"
            @click="openModalCrear()">
            <i class="ti ti-plus me-1"></i>
            Nuevo
        </button>

    </div>

    <div class="datatables">
        <div class="table-responsive">

            <table
                id="table-estaciones"
                class="table table-striped table-bordered align-middle">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>
                            Nombre
                        </th>

                        <th>
                            Permiso CRE
                        </th>

                        <th>
                            Razón Social
                        </th>

                        <th>
                            RFC
                        </th>

                        <th class="text-center">
                            Estatus
                        </th>

                        <th
                            class="text-center"
                            style="width:50px;">
                            <i class="ti ti-dots-vertical text-muted fs-6"></i>
                        </th>

                    </tr>

                </thead>

                <tbody></tbody>

            </table>

        </div>
    </div>


    <div
        class="modal fade"
        id="modalEstacion"
        tabindex="-1"
        aria-hidden="true">

        <div
            class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content">

                <!-- Header -->
                <div class="modal-header bg-primary">

                    <div>

                        <h5
                            class="modal-title fw-semibold text-white"
                            x-text="
                                modo === 'create'
                                    ? 'Nueva estación'
                                    : 'Editar estación'
                            "></h5>

                        <small class="text-white">
                            Ingresa la información de la estación
                        </small>

                    </div>


                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>

                </div>


                <!-- Body -->
                <div class="modal-body">

                    <!-- Información principal -->
                    <div class="mb-4">

                        <div class="d-flex align-items-center mb-3">

                            <div
                                class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center me-3"
                                style="width:42px;height:42px;min-width:42px;">
                                <i class="ti ti-gas-station fs-5"></i>
                            </div>

                            <div>

                                <h6 class="fw-semibold mb-0">
                                    Información principal
                                </h6>

                                <small class="text-muted">
                                    Datos generales de la estación
                                </small>

                            </div>

                        </div>


                        <div class="row">

                            <!-- Nombre -->
                            <div class="col-lg-4 col-md-6 mb-3">

                                <label class="form-label">
                                    Nombre
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    :class="{'is-invalid': errors.nombre}"
                                    x-model="form.nombre">

                            </div>


                            <!-- ES -->
                            <div class="col-lg-4 col-md-6 mb-3">

                                <label class="form-label">
                                    E.S.
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="form.es">

                            </div>


                            <!-- Permiso CRE -->
                            <div class="col-lg-4 col-md-6 mb-3">

                                <label class="form-label">
                                    Permiso CRE
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    :class="{'is-invalid': errors.permisocre}"
                                    x-model="form.permisocre">

                            </div>


                            <!-- Razón social -->
                            <div class="col-lg-8 col-md-6 mb-3">

                                <label class="form-label">
                                    Razón Social
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    :class="{'is-invalid': errors.razonsocial}"
                                    x-model="form.razonsocial">

                            </div>


                            <!-- RFC -->
                            <div class="col-lg-4 col-md-6 mb-3">

                                <label class="form-label">
                                    RFC
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    class="form-control text-uppercase"
                                    :class="{'is-invalid': errors.rfc}"
                                    x-model="form.rfc">

                            </div>

                        </div>

                    </div>


                    <hr class="my-4">


                    <!-- Dirección -->
                    <div class="mb-4">

                        <div class="d-flex align-items-center mb-3">

                            <div
                                class="bg-success-subtle text-success rounded-3 d-flex align-items-center justify-content-center me-3"
                                style="width:42px;height:42px;min-width:42px;">
                                <i class="ti ti-map-pin fs-5"></i>
                            </div>

                            <div>

                                <h6 class="fw-semibold mb-0">
                                    Ubicación
                                </h6>

                                <small class="text-muted">
                                    Dirección de la estación
                                </small>

                            </div>

                        </div>


                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Estado
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    :class="{'is-invalid': errors.di_estado}"
                                    x-model="form.di_estado">

                            </div>


                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Municipio
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    :class="{'is-invalid': errors.di_municipio}"
                                    x-model="form.di_municipio">

                            </div>


                            <div class="col-12 mb-3">

                                <label class="form-label">
                                    Dirección completa
                                    <span class="text-danger">*</span>
                                </label>

                                <textarea
                                    class="form-control"
                                    rows="2"
                                    :class="{'is-invalid': errors.direccioncompleta}"
                                    x-model="form.direccioncompleta"></textarea>

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Distancia máxima
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="form-control"
                                        :class="{'is-invalid': errors.distmax}"
                                        x-model="form.distmax">

                                    <span class="input-group-text">
                                        m
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>


                    <hr class="my-4">


                    <!-- Legal -->
                    <div class="mb-4">

                        <div class="d-flex align-items-center mb-3">

                            <div
                                class="bg-warning-subtle text-warning rounded-3 d-flex align-items-center justify-content-center me-3"
                                style="width:42px;height:42px;min-width:42px;">
                                <i class="ti ti-file-certificate fs-5"></i>
                            </div>

                            <div>

                                <h6 class="fw-semibold mb-0">
                                    Información legal
                                </h6>

                                <small class="text-muted">
                                    Datos administrativos y autorización
                                </small>

                            </div>

                        </div>


                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Apoderado legal
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    :class="{'is-invalid': errors.apoderado_legal}"
                                    x-model="form.apoderado_legal">

                            </div>


                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Fecha de autorización
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="date"
                                    class="form-control"
                                    :class="{'is-invalid': errors.fecha_autorizacion}"
                                    x-model="form.fecha_autorizacion">

                            </div>


                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Franquicia
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="form.franquicia">

                            </div>

                        </div>

                    </div>


                    <hr class="my-4">


                    <!-- Productos -->
                    <div>

                        <div class="d-flex align-items-center mb-3">

                            <div
                                class="bg-info-subtle text-info rounded-3 d-flex align-items-center justify-content-center me-3"
                                style="width:42px;height:42px;min-width:42px;">
                                <i class="ti ti-droplet fs-5"></i>
                            </div>

                            <div>

                                <h6 class="fw-semibold mb-0">
                                    Configuración
                                </h6>

                                <small class="text-muted">
                                    Productos y configuración operativa
                                </small>

                            </div>

                        </div>


                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Producto 1
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="form.producto_uno">

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Producto 2
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="form.producto_dos">

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Producto 3
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="form.producto_tres">

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    SASISOPA
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="form.sasisopa">

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Organigrama
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="form.organigrama">

                            </div>


                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Volumétrico
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="form.volumetrico">

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    No. registro de generador
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    :class="{'is-invalid': errors.noregistro_generador}"
                                    x-model="form.noregistro_generador">

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Categoría
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    :class="{'is-invalid': errors.categoria}"
                                    x-model="form.categoria">

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Footer -->
                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        :disabled="loading">
                        <i class="ti ti-x"></i> Cancelar
                    </button>


                    <button
                        type="button"
                        class="btn btn-success"
                        @click="guardar()"
                        :disabled="loading">

                        <template x-if="!loading">

                            <span>
                                <i class="ti ti-check me-1"></i>

                                <span
                                    x-text="
                                        modo === 'create'
                                            ? 'Crear estación'
                                            : 'Guardar cambios'
                                    "></span>
                            </span>

                        </template>


                        <template x-if="loading">

                            <span>
                                <span
                                    class="spinner-border spinner-border-sm me-2"></span>

                                Guardando...
                            </span>

                        </template>

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>