<div id="container" class="pb-4" x-data="{ ...actions(), ...personal()}" data-layout="<?= $layout ?>">

    <script>
        window.__PUESTOS__ = <?= json_encode($puestos, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <div class="text-end mt-2">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <?=
                !empty($permisos['crear']) ?
                    '<li>
                  <a class="dropdown-item" @click="openCreate()"><i class="ti ti-plus"></i> Agregar</a>
                </li>'
                    : ''
                ?>
                <li>
                    <a class="dropdown-item" href="<?= ($layout == 'sgm') ? '/personal/sgm/pdf' : '/uploads/archivos/renuncia/' . $renuncia; ?>" download><i class="ti ti-download"></i> Descargar</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="datatables mt-3">
        <div class="table-responsive">
            <table id="table-personal" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre Usuario</th>
                        <th>Puesto</th>
                        <th>Telefono</th>
                        <th>Email</th>
                        <th>Usuario</th>
                        <?= ($layout == 'sgm') ? '<th>Grado de responsabilidad SGM</th>' : '' ?>
                        <th>Estatus</th>
                        <th class="text-center">
                            <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
                        </th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade"
        id="modalPersonal"
        tabindex="-1"
        data-bs-backdrop="static"
        data-bs-keyboard="false">

        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">

            <div class="modal-content">

                <!-- HEADER -->
                <div class="modal-header modal-colored-header bg-primary text-white">

                    <h4 class="modal-title text-white">

                        <span class="ms-2"
                            x-text="mode === 'create'
                                ? 'Agregar Usuario'
                                : 'Editar Usuario'">
                        </span>

                    </h4>

                    <button class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                    </button>

                </div>

                <!-- BODY -->
                <div class="modal-body">

                    <div class="row g-3">

                        <!-- Nombre -->
                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                Nombre completo
                            </label>

                            <input type="text"
                                class="form-control"
                                placeholder="Nombre completo"
                                x-model="nombre"
                                @input="errors.nombre = false"
                                :class="errors.nombre ? 'is-invalid' : ''">

                        </div>

                        <!-- Telefono -->
                        <div class="col-md-6">

                            <label class="form-label fw-semibold">

                                Teléfono

                            </label>

                            <input type="text"
                                class="form-control"
                                placeholder="Teléfono"
                                x-model="telefono">

                        </div>

                        <!-- Email -->
                        <div class="col-md-6">

                            <label class="form-label fw-semibold">

                                Correo electrónico

                            </label>

                            <input type="email"
                                class="form-control"
                                placeholder="Correo electrónico"
                                x-model="email"
                                @input="errors.email = false"
                                :class="errors.email ? 'is-invalid' : ''">

                        </div>

                        <!-- Fecha -->
                        <div class="col-md-6">

                            <label class="form-label fw-semibold">

                                Fecha de ingreso

                            </label>

                            <input type="date"
                                class="form-control"
                                x-model="fecha_ingreso"
                                @input="errors.fecha_ingreso = false"
                                :class="errors.fecha_ingreso ? 'is-invalid' : ''">

                        </div>

                        <!-- Puesto -->
                        <div class="col-md-6">

                            <label class="form-label fw-semibold">

                                Puesto

                            </label>

                            <select class="form-select"
                                x-model="id_puesto"
                                @change="errors.id_puesto = false"
                                :class="errors.id_puesto ? 'is-invalid' : ''">

                                <option value="">
                                    Seleccione...
                                </option>

                                <?php foreach ($puestos as $puesto): ?>
                                    <option value="<?= $puesto->id ?>">
                                        <?= htmlspecialchars($puesto->tipo_puesto) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                        </div>

                        <!-- Usuario -->
                        <div class="col-md-10">

                            <label class="form-label fw-semibold">

                                Usuario

                            </label>

                            <input type="text"
                                class="form-control"
                                placeholder="Usuario"
                                x-model="usuario"
                                @input="errors.usuario = false"
                                :class="errors.usuario ? 'is-invalid' : ''">

                        </div>

                        <div class="col-md-2">

                            <label class="form-label">

                                &nbsp;

                            </label>

                            <button class="btn btn-outline-primary w-100"
                                type="button"
                                @click="usuarioAleatorio()">

                                <i class="ti ti-refresh"></i>

                            </button>

                        </div>

                        <!-- Password -->
                        <div class="col-md-5">

                            <label class="form-label fw-semibold">

                                Contraseña

                            </label>

                            <input type="text"
                                class="form-control"
                                x-model="password">

                        </div>

                        <div class="col-md-2">

                            <label class="form-label">

                                &nbsp;

                            </label>

                            <button class="btn btn-outline-primary w-100"
                                type="button"
                                @click="passwordAleatorio()">

                                <i class="ti ti-refresh"></i>

                            </button>

                        </div>

                        <div class="col-md-5">

                            <label class="form-label fw-semibold">

                                Confirmar contraseña

                            </label>

                            <input type="password"
                                class="form-control"
                                x-model="password_confirmacion">

                        </div>

                    </div>

                    <!-- Alertas -->

                    <div class="alert alert-danger mt-4 mb-0"
                        x-show="error!=''"
                        x-text="error">
                    </div>

                </div>

                <!-- FOOTER -->

                <div class="modal-footer">

                    <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        @click="resetModal()">

                        <i class="ti ti-x"></i> Cancelar

                    </button>

                    <button class="btn btn-success"
                        @click="submit()"
                        :disabled="loading">

                        <span x-show="!loading">

                            <i class="ti ti-check"></i> Guardar

                        </span>

                        <span x-show="loading">

                            <span class="spinner-border spinner-border-sm me-2">
                            </span>

                            Guardando...

                        </span>

                    </button>

                </div>

            </div>

        </div>

    </div>


</div>