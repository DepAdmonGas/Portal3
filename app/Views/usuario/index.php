<div
    id="container"
    class="pb-4"
    x-data="{
        ...actions(),
        ...usuarios(
            <?= json_encode($idestacion ? (int) $idestacion : null) ?>
        )
    }">

    <div
        class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4 mt-3">

        <div class="w-100" style="max-width: 420px;">

            <select
                id="filtroEstacion"
                class="form-select"
                x-model="filtroEstacion"
                @change="cambiarEstacion()">

                <option value="">
                    Todas las estaciones
                </option>

                <?php foreach ($estaciones as $estacion): ?>

                    <option
                        value="<?= (int) $estacion->id ?>">
                        <?= htmlspecialchars(
                            $estacion->razonsocial
                                ?: $estacion->nombre,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div>

            <button
                type="button"
                class="btn btn-primary"
                @click="openModalCrear()">
                <i class="ti ti-user-plus me-1"></i>
                Nuevo
            </button>

        </div>

    </div>

    <div class="datatables">
        <div class="table-responsive">

            <table
                id="table-usuarios"
                class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>
                            Nombre
                        </th>

                        <th>
                            Correo
                        </th>

                        <th>
                            Teléfono
                        </th>

                        <th>
                            Puesto
                        </th>

                        <th>
                            Estación
                        </th>

                        <th>
                            Estatus
                        </th>

                        <th class="text-center">

                            <a class="text-muted">
                                <i
                                    class="ti ti-dots-vertical fs-6"></i>
                            </a>

                        </th>

                    </tr>

                </thead>

                <tbody></tbody>

            </table>

        </div>
    </div>

    <div
        class="modal fade"
        id="modalUsuario"
        tabindex="-1"
        aria-labelledby="modalUsuarioLabel"
        aria-hidden="true">

        <div
            class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <div>

                        <h5
                            class="modal-title fw-semibold text-white"
                            id="modalUsuarioLabel"
                            x-text="
                            modo === 'create'
                                ? 'Nuevo usuario'
                                : 'Editar usuario'
                        "></h5>

                        <small class="text-white">
                            Información y configuración del usuario
                        </small>

                    </div>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>

                </div>


                <!-- =================================================
            BODY
            ================================================== -->
                <div class="modal-body">


                    <!-- =================================================
                CUENTA
                ================================================== -->
                    <div class="d-flex align-items-center mb-3">

                        <div
                            class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center me-3"
                            style="
                            width: 42px;
                            height: 42px;
                            min-width: 42px;
                        ">
                            <i class="ti ti-user fs-5"></i>
                        </div>

                        <div>

                            <h6 class="fw-semibold mb-0">
                                Información de la cuenta
                            </h6>

                            <small class="text-muted">
                                Datos principales y credenciales
                            </small>

                        </div>

                    </div>


                    <div class="row">

                        <!-- NOMBRE -->
                        <div class="col-lg-6 mb-3">

                            <label class="form-label">
                                Nombre
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                :class="{
                                'is-invalid':
                                    errors.nombre
                            }"
                                @input="errors.nombre = false"
                                x-model="form.nombre">

                            <div class="invalid-feedback">
                                Ingresa el nombre.
                            </div>

                        </div>


                        <!-- TELEFONO -->
                        <div class="col-lg-3 col-md-6 mb-3">

                            <label class="form-label">
                                Teléfono
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                x-model="form.telefono">

                        </div>


                        <!-- EMAIL -->
                        <div class="col-lg-3 col-md-6 mb-3">

                            <label class="form-label">
                                Correo electrónico
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                :class="{
                                'is-invalid':
                                    errors.email
                            }"
                                x-model="form.email">

                            <div class="invalid-feedback">
                                Ingresa un correo válido.
                            </div>

                        </div>


                        <!-- USUARIO -->
                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Usuario
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                :class="{
                                'is-invalid':
                                    errors.usuario
                            }"
                                @input="errors.usuario = false"
                                x-model="form.usuario"
                                autocomplete="off">

                            <div class="invalid-feedback">
                                Ingresa el usuario.
                            </div>

                        </div>


                        <!-- PASSWORD -->
                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Contraseña

                                <span
                                    class="text-danger"
                                    x-show="
                                    modo === 'create'
                                ">
                                    *
                                </span>

                            </label>

                            <input
                                type="password"
                                class="form-control"
                                :class="{
                                'is-invalid':
                                    errors.password
                            }"
                                @input="errors.password = false"
                                x-model="form.password"
                                autocomplete="new-password">

                            <div class="invalid-feedback">
                                Ingresa una contraseña.
                            </div>

                            <small
                                class="text-muted"
                                x-show="
                                modo === 'edit'
                            ">
                                Déjala vacía para conservar la contraseña actual.
                            </small>

                        </div>

                    </div>


                    <hr class="my-4">


                    <!-- =================================================
                ASIGNACION
                ================================================== -->
                    <div class="d-flex align-items-center mb-3">

                        <div
                            class="bg-success-subtle text-success rounded-3 d-flex align-items-center justify-content-center me-3"
                            style="
                            width: 42px;
                            height: 42px;
                            min-width: 42px;
                        ">
                            <i class="ti ti-building-community fs-5"></i>
                        </div>

                        <div>

                            <h6 class="fw-semibold mb-0">
                                Asignación
                            </h6>

                            <small class="text-muted">
                                Estación y puesto del usuario
                            </small>

                        </div>

                    </div>


                    <div class="row">

                        <!-- ESTACION -->
                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Estación
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-select"
                                :class="{
                                'is-invalid':
                                    errors.id_gas
                            }"
                                @input="errors.id_gas = false"
                                x-model="form.id_gas">

                                <option value="">
                                    Selecciona una estación
                                </option>

                                <?php foreach ($estaciones as $estacion): ?>

                                    <option
                                        value="<?= (int) $estacion->id ?>">
                                        <?= htmlspecialchars(
                                            $estacion->razonsocial
                                                ?: $estacion->nombre,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="invalid-feedback">
                                Selecciona una estación.
                            </div>

                            <small
                                class="text-muted"
                                x-show="
                                filtroEstacion
                                && modo === 'create'
                            ">
                                La estación fue seleccionada automáticamente según el filtro actual.
                            </small>

                        </div>


                        <!-- PUESTO -->
                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Puesto
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-select"
                                :class="{
                                'is-invalid':
                                    errors.id_puesto
                            }"
                                @input="errors.id_puesto = false"
                                x-model="form.id_puesto">

                                <option value="">
                                    Selecciona un puesto
                                </option>

                                <?php foreach ($puestos as $puesto): ?>

                                    <option
                                        value="<?= (int) $puesto->id ?>">
                                        <?= htmlspecialchars(
                                            $puesto->tipo_puesto,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="invalid-feedback">
                                Selecciona un puesto.
                            </div>

                        </div>

                    </div>


                    <hr class="my-4">

                    <div class="d-flex align-items-center mb-3">

                        <div
                            class="bg-info-subtle text-info rounded-3 d-flex align-items-center justify-content-center me-3"
                            style="
                            width: 42px;
                            height: 42px;
                            min-width: 42px;
                        ">
                            <i class="ti ti-id fs-5"></i>
                        </div>

                        <div>

                            <h6 class="fw-semibold mb-0">
                                Información personal
                            </h6>

                            <small class="text-muted">
                                Datos personales del usuario
                            </small>

                        </div>

                    </div>


                    <div class="row">

                        <!-- FECHA NACIMIENTO -->
                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Fecha de nacimiento
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                :class="{
                                'is-invalid':
                                    errors.fecha_nacimiento
                            }"
                                @input="errors.fecha_nacimiento = false"
                                x-model="form.fecha_nacimiento">

                            <div class="invalid-feedback">
                                Selecciona la fecha de nacimiento.
                            </div>

                        </div>


                        <!-- ESTADO CIVIL -->
                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Estado civil
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                :class="{
                                'is-invalid':
                                    errors.estado_civil
                            }"
                                x-model="form.estado_civil">

                            <div class="invalid-feedback">
                                Ingresa el estado civil.
                            </div>

                        </div>


                        <!-- SEGURO SOCIAL -->
                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Seguro social
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                :class="{
                                'is-invalid':
                                    errors.seguro_social
                            }"
                                x-model="form.seguro_social">

                            <div class="invalid-feedback">
                                Ingresa el número de seguro social.
                            </div>

                        </div>


                        <!-- DOMICILIO -->
                        <div class="col-12 mb-3">

                            <label class="form-label">
                                Domicilio
                            </label>

                            <textarea
                                class="form-control"
                                rows="3"
                                :class="{
                                'is-invalid':
                                    errors.domicilio
                            }"
                                x-model="form.domicilio"></textarea>

                            <div class="invalid-feedback">
                                Ingresa el domicilio.
                            </div>

                        </div>

                    </div>


                    <hr class="my-4">
                    <div class="d-flex align-items-center mb-3">

                        <div
                            class="bg-warning-subtle text-warning rounded-3 d-flex align-items-center justify-content-center me-3"
                            style="
                            width: 42px;
                            height: 42px;
                            min-width: 42px;
                        ">
                            <i class="ti ti-briefcase fs-5"></i>
                        </div>

                        <div>

                            <h6 class="fw-semibold mb-0">
                                Información laboral
                            </h6>

                            <small class="text-muted">
                                Información relacionada con su puesto
                            </small>

                        </div>

                    </div>


                    <div class="row">

                        <!-- FECHA INGRESO -->
                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Fecha de ingreso
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                :class="{
                                'is-invalid':
                                    errors.fecha_ingreso
                            }"
                                @input="errors.fecha_ingreso = false"
                                x-model="form.fecha_ingreso">

                            <div class="invalid-feedback">
                                Selecciona la fecha de ingreso.
                            </div>

                        </div>


                        <!-- RESPONSABILIDAD SGM -->
                        <div class="col-md-8 mb-3">

                            <label class="form-label">
                                Responsabilidad SGM
                            </label>

                            <textarea
                                class="form-control"
                                rows="3"
                                :class="{
                                'is-invalid':
                                    errors.responsabilidad_sgm
                            }"
                                x-model="form.responsabilidad_sgm"></textarea>

                            <div class="invalid-feedback">
                                Ingresa la responsabilidad SGM.
                            </div>

                        </div>

                    </div>

                </div>

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
                                        ? 'Crear usuario'
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