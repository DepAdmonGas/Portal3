<div
    x-data="cambiarPassword()"
    x-on:abrir-modal-password.window="abrirModal()">

    <div class="container-fluid mt-4">

        <div class="row">

            <div class="col-lg-4">

                <div class="card">

                    <div class="card-body text-center">

                        <!-- Avatar -->
                        <div
                            class="position-relative d-inline-block mb-3">

                            <?php if (!empty($user->foto)): ?>

                                <img
                                    src="<?= htmlspecialchars($user->foto) ?>"
                                    alt="Foto de perfil"
                                    class="rounded-circle object-fit-cover"
                                    width="120"
                                    height="120">

                            <?php else: ?>

                                <div
                                    class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center mx-auto"
                                    style="width:120px;height:120px;">
                                    <i class="ti ti-user fs-10"></i>
                                </div>

                            <?php endif; ?>


                            <span
                                class="position-absolute bottom-0 end-0 p-2 bg-success border border-3 border-white rounded-circle"
                                title="Usuario activo"></span>

                        </div>


                        <!-- Nombre -->
                        <h4 class="fw-semibold mb-1">
                            <?= htmlspecialchars($user->nombre ?? 'Usuario') ?>
                        </h4>


                        <!-- Puesto -->
                        <p class="text-muted mb-3">
                            <?= htmlspecialchars(
                                $user->puesto->tipo_puesto
                                    ?? 'Puesto no asignado'
                            ) ?>
                        </p>


                        <!-- Datos rápidos -->
                        <div
                            class="d-flex justify-content-center gap-2 flex-wrap mb-4">

                            <span class="badge bg-primary-subtle text-primary">

                                <i class="ti ti-id me-1"></i>

                                ID: 00<?= (int) ($user->id ?? 0) ?>

                            </span>


                            <?php if ($user->estatus == 0): ?>

                                <span class="badge bg-success-subtle text-success">

                                    <i class="ti ti-circle-check me-1"></i>

                                    Activo

                                </span>

                            <?php else: ?>

                                <span class="badge bg-danger-subtle text-danger">

                                    <i class="ti ti-circle-x me-1"></i>

                                    Inactivo

                                </span>

                            <?php endif; ?>

                        </div>


                        <!-- Contacto -->
                        <div class="text-start">

                            <div
                                class="d-flex align-items-center gap-3 py-3 border-top">

                                <div
                                    class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width:42px;height:42px;">
                                    <i class="ti ti-mail fs-5"></i>
                                </div>

                                <div class="overflow-hidden">

                                    <small class="text-muted d-block">
                                        Correo electrónico
                                    </small>

                                    <span class="fw-medium text-break">
                                        <?= htmlspecialchars(
                                            $user->email
                                                ?? 'No registrado'
                                        ) ?>
                                    </span>

                                </div>

                            </div>


                            <div
                                class="d-flex align-items-center gap-3 py-3 border-top">

                                <div
                                    class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width:42px;height:42px;">
                                    <i class="ti ti-phone fs-5"></i>
                                </div>

                                <div>

                                    <small class="text-muted d-block">
                                        Teléfono
                                    </small>

                                    <span class="fw-medium">
                                        <?= htmlspecialchars(
                                            $user->telefono
                                                ?? 'No registrado'
                                        ) ?>
                                    </span>

                                </div>

                            </div>


                            <div
                                class="d-flex align-items-center gap-3 py-3 border-top">

                                <div
                                    class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width:42px;height:42px;">
                                    <i class="ti ti-at fs-5"></i>
                                </div>

                                <div>

                                    <small class="text-muted d-block">
                                        Usuario
                                    </small>

                                    <span class="fw-medium">
                                        <?= htmlspecialchars(
                                            $user->usuario
                                                ?? 'No registrado'
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        </div>


                        <!-- Acción -->
                        <div class="d-grid mt-3">

                            <button
                                type="button"
                                class="btn btn-primary">
                                <i class="ti ti-edit me-2"></i>
                                Editar perfil
                            </button>

                        </div>

                    </div>

                </div>

            </div>


            <!-- ========================================================
        | Columna derecha
        ======================================================== -->
            <div class="col-lg-8">

                <!-- ====================================================
            | Información personal
            ==================================================== -->
                <div class="card">

                    <div class="card-header">

                        <div class="d-flex align-items-center gap-2">

                            <span
                                class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"
                                style="width:38px;height:38px;">
                                <i class="ti ti-user-circle fs-5"></i>
                            </span>

                            <div>

                                <h5 class="card-title mb-0">
                                    Información personal
                                </h5>

                                <small class="text-muted">
                                    Datos generales del usuario
                                </small>

                            </div>

                        </div>

                    </div>


                    <div class="card-body">

                        <div class="row g-4">

                            <div class="col-md-6">

                                <label class="form-label text-muted">
                                    Nombre completo
                                </label>

                                <div class="fw-semibold">
                                    <?= htmlspecialchars(
                                        $user->nombre
                                            ?? 'No registrado'
                                    ) ?>
                                </div>

                            </div>


                            <div class="col-md-6">

                                <label class="form-label text-muted">
                                    Fecha de nacimiento
                                </label>

                                <div class="fw-semibold">

                                    <?= !empty($user->fecha_nacimiento)
                                        ? date(
                                            'd/m/Y',
                                            strtotime(
                                                $user->fecha_nacimiento
                                            )
                                        )
                                        : 'No registrada'
                                    ?>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <label class="form-label text-muted">
                                    Estado civil
                                </label>

                                <div class="fw-semibold">
                                    <?= htmlspecialchars(
                                        $user->estado_civil
                                            ?? 'No registrado'
                                    ) ?>
                                </div>

                            </div>


                            <div class="col-md-6">

                                <label class="form-label text-muted">
                                    Seguro social
                                </label>

                                <div class="fw-semibold">
                                    <?= htmlspecialchars(
                                        $user->seguro_social
                                            ?? 'No registrado'
                                    ) ?>
                                </div>

                            </div>


                            <div class="col-12">

                                <label class="form-label text-muted">
                                    Domicilio
                                </label>

                                <div class="fw-semibold">
                                    <?= htmlspecialchars(
                                        $user->domicilio
                                            ?? 'No registrado'
                                    ) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- ====================================================
            | Información laboral
            ==================================================== -->
                <div class="card">

                    <div class="card-header">

                        <div class="d-flex align-items-center gap-2">

                            <span
                                class="bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center"
                                style="width:38px;height:38px;">
                                <i class="ti ti-briefcase fs-5"></i>
                            </span>

                            <div>

                                <h5 class="card-title mb-0">
                                    Información laboral
                                </h5>

                                <small class="text-muted">
                                    Datos relacionados con su puesto y estación
                                </small>

                            </div>

                        </div>

                    </div>


                    <div class="card-body">

                        <div class="row g-4">

                            <div class="col-md-6">

                                <label class="form-label text-muted">
                                    Estación
                                </label>

                                <div class="fw-semibold">

                                    <?= htmlspecialchars(
                                        $user->estacion->nombre
                                            ?? $user->estacion->localidad
                                            ?? 'No asignada'
                                    ) ?>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <label class="form-label text-muted">
                                    Puesto
                                </label>

                                <div class="fw-semibold">

                                    <?= htmlspecialchars(
                                        $user->puesto->tipo_puesto
                                            ?? 'No asignado'
                                    ) ?>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <label class="form-label text-muted">
                                    Fecha de ingreso
                                </label>

                                <div class="fw-semibold">
                                    <?php
                                    $fechaIngreso = $user->fecha_ingreso ?? null;

                                    if (
                                        !empty($fechaIngreso) &&
                                        $fechaIngreso !== '0000-00-00' &&
                                        $fechaIngreso !== '0000-00-00 00:00:00' &&
                                        strtotime($fechaIngreso) !== false
                                    ) {
                                        echo date('d/m/Y', strtotime($fechaIngreso));
                                    } else {
                                        echo 'No registrada';
                                    }
                                    ?>
                                </div>

                            </div>


                            <div class="col-md-6">

                                <label class="form-label text-muted">
                                    Responsabilidad SGM
                                </label>

                                <div class="fw-semibold">

                                    <?= htmlspecialchars(
                                        $user->responsabilidad_sgm
                                            ?? 'No registrada'
                                    ) ?>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- ====================================================
            | Seguridad
            ==================================================== -->
                <div class="card">

                    <div class="card-header">

                        <div class="d-flex align-items-center gap-2">

                            <span
                                class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center"
                                style="width:38px;height:38px;">
                                <i class="ti ti-shield-lock fs-5"></i>
                            </span>

                            <div>

                                <h5 class="card-title mb-0">
                                    Seguridad
                                </h5>

                                <small class="text-muted">
                                    Configuración de acceso a la cuenta
                                </small>

                            </div>

                        </div>

                    </div>


                    <div class="card-body">

                        <div
                            class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-2">

                            <div class="d-flex align-items-center gap-3">

                                <span
                                    class="bg-light rounded-circle d-flex align-items-center justify-content-center"
                                    style="width:46px;height:46px;">
                                    <i class="ti ti-key fs-5"></i>
                                </span>

                                <div>

                                    <h6 class="mb-1">
                                        Contraseña
                                    </h6>

                                    <small class="text-muted">
                                        Actualiza tu contraseña de acceso.
                                    </small>

                                </div>

                            </div>


                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                @click="$dispatch('abrir-modal-password')">
                                <i class="ti ti-key me-2"></i>
                                Cambiar contraseña
                            </button>

                        </div>


                        <hr>


                        <div
                            class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-2">

                            <div class="d-flex align-items-center gap-3">

                                <span
                                    class="bg-light rounded-circle d-flex align-items-center justify-content-center"
                                    style="width:46px;height:46px;">
                                    <i class="ti ti-device-mobile-check fs-5"></i>
                                </span>

                                <div>

                                    <div class="d-flex align-items-center gap-2">

                                        <h6 class="mb-1">
                                            Autenticación en dos pasos
                                        </h6>


                                        <?php if (!empty($user->two_factor_enabled)): ?>

                                            <span
                                                class="badge bg-success-subtle text-success">
                                                Activa
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge bg-secondary-subtle text-secondary">
                                                Inactiva
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                    <small class="text-muted">
                                        Agrega una capa adicional de seguridad.
                                    </small>

                                </div>

                            </div>


                            <?php if (!empty($user->two_factor_enabled)): ?>

                                <button
                                    type="button"
                                    class="btn btn-outline-danger">
                                    Desactivar
                                </button>

                            <?php else: ?>

                                <button
                                    type="button"
                                    class="btn btn-outline-success">
                                    Activar
                                </button>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- ====================================================
            | Firma
            ==================================================== -->
                <div class="card">

                    <div class="card-header">

                        <div class="d-flex align-items-center gap-2">

                            <span
                                class="bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center"
                                style="width:38px;height:38px;">
                                <i class="ti ti-signature fs-5"></i>
                            </span>

                            <div>

                                <h5 class="card-title mb-0">
                                    Firma
                                </h5>

                                <small class="text-muted">
                                    Firma asociada al usuario
                                </small>

                            </div>

                        </div>

                    </div>


                    <div class="card-body">

                        <?php if (!empty($user->firma)): ?>

                            <div
                                class="border rounded-3 p-4 text-center bg-light-subtle">

                                <img
                                    src="<?= htmlspecialchars(
                                                '/assets/img/firmas/'
                                                    . $user->firma
                                            ) ?>"
                                    alt="Firma"
                                    class="img-fluid"
                                    style="max-height:120px;">

                            </div>

                        <?php else: ?>

                            <div
                                class="border border-dashed rounded-3 p-5 text-center">

                                <div
                                    class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                                    style="width:58px;height:58px;">
                                    <i class="ti ti-signature fs-7 text-muted"></i>
                                </div>

                                <h6 class="mb-1">
                                    Sin firma registrada
                                </h6>

                                <p class="text-muted mb-3">
                                    Este usuario aún no tiene una firma asociada.
                                </p>

                                <button
                                    type="button"
                                    class="btn btn-outline-primary">
                                    <i class="ti ti-upload me-2"></i>
                                    Subir firma
                                </button>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <div
        class="modal fade"
        id="modalCambiarPassword"
        tabindex="-1"
        aria-labelledby="modalCambiarPasswordLabel"
        aria-hidden="true"
        x-ref="modal">
        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <!-- Header -->
                <div class="modal-header bg-primary">

                    <div class="d-flex align-items-center gap-3">

                        <span
                            class="bg-light-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"
                            style="width:42px;height:42px;">
                            <i class="ti ti-key fs-5"></i>
                        </span>

                        <div>
                            <h5
                                class="modal-title mb-0 text-white"
                                id="modalCambiarPasswordLabel">
                                Cambiar contraseña
                            </h5>

                            <small class="text-white">
                                Actualiza la contraseña de acceso a tu cuenta.
                            </small>
                        </div>

                    </div>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"
                        :disabled="guardando"></button>

                </div>

                <form @submit.prevent="guardar">

                    <div class="modal-body">

                        <!-- Contraseña actual -->
                        <div class="mb-4">

                            <label
                                for="password_actual"
                                class="form-label">
                                Contraseña actual
                            </label>

                            <div class="input-group">

                                <input
                                    :type="mostrarActual ? 'text' : 'password'"
                                    id="password_actual"
                                    class="form-control"
                                    :class="{
                                        'is-invalid': errores.password_actual
                                    }"
                                    x-model="form.password_actual"
                                    autocomplete="current-password"
                                    placeholder="Ingresa tu contraseña actual"
                                    :disabled="guardando"
                                    required>

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    @click="mostrarActual = !mostrarActual"
                                    tabindex="-1">
                                    <i
                                        class="ti"
                                        :class="
                                            mostrarActual
                                                ? 'ti-eye-off'
                                                : 'ti-eye'
                                        "></i>
                                </button>

                            </div>

                            <div
                                class="invalid-feedback d-block"
                                x-show="errores.password_actual"
                                x-text="errores.password_actual"></div>

                        </div>


                        <!-- Nueva contraseña -->
                        <div class="mb-4">

                            <label
                                for="password_nueva"
                                class="form-label">
                                Nueva contraseña
                            </label>

                            <div class="input-group">

                                <input
                                    :type="mostrarNueva ? 'text' : 'password'"
                                    id="password_nueva"
                                    class="form-control"
                                    :class="{
                                        'is-invalid': errores.password_nueva
                                    }"
                                    x-model="form.password_nueva"
                                    autocomplete="new-password"
                                    placeholder="Mínimo 8 caracteres"
                                    minlength="8"
                                    :disabled="guardando"
                                    required>

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    @click="mostrarNueva = !mostrarNueva"
                                    tabindex="-1">
                                    <i
                                        class="ti"
                                        :class="
                                            mostrarNueva
                                                ? 'ti-eye-off'
                                                : 'ti-eye'
                                        "></i>
                                </button>

                            </div>

                            <div
                                class="invalid-feedback d-block"
                                x-show="errores.password_nueva"
                                x-text="errores.password_nueva"></div>

                            <div class="form-text">
                                Utiliza al menos 8 caracteres.
                            </div>

                        </div>


                        <!-- Confirmar contraseña -->
                        <div>

                            <label
                                for="password_confirmacion"
                                class="form-label">
                                Confirmar nueva contraseña
                            </label>

                            <div class="input-group">

                                <input
                                    :type="mostrarConfirmacion ? 'text' : 'password'"
                                    id="password_confirmacion"
                                    class="form-control"
                                    :class="{
                                        'is-invalid': errores.password_confirmacion
                                    }"
                                    x-model="form.password_confirmacion"
                                    autocomplete="new-password"
                                    placeholder="Repite la nueva contraseña"
                                    minlength="8"
                                    :disabled="guardando"
                                    required>

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    @click="
                                        mostrarConfirmacion =
                                            !mostrarConfirmacion
                                    "
                                    tabindex="-1">
                                    <i
                                        class="ti"
                                        :class="
                                            mostrarConfirmacion
                                                ? 'ti-eye-off'
                                                : 'ti-eye'
                                        "></i>
                                </button>

                            </div>

                            <div
                                class="invalid-feedback d-block"
                                x-show="errores.password_confirmacion"
                                x-text="errores.password_confirmacion"></div>

                        </div>


                        <!-- Error general -->
                        <div
                            class="alert alert-danger mt-4 mb-0"
                            role="alert"
                            x-show="errorGeneral"
                            x-transition>
                            <div class="d-flex align-items-start gap-2">

                                <i class="ti ti-alert-circle fs-5"></i>

                                <span x-text="errorGeneral"></span>

                            </div>
                        </div>

                    </div>


                    <!-- Footer -->
                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn bg-danger-subtle text-danger"
                            data-bs-dismiss="modal"
                            :disabled="guardando">
                            <i class="ti ti-x"></i> Cancelar
                        </button>

                        <button
                            type="submit"
                            class="btn btn-success"
                            :disabled="guardando">
                            <template x-if="!guardando">
                                <span>
                                    <i class="ti ti-device-floppy me-2"></i>
                                    Cambiar contraseña
                                </span>
                            </template>

                            <template x-if="guardando">
                                <span>
                                    <span
                                        class="spinner-border spinner-border-sm me-2"
                                        aria-hidden="true"></span>

                                    Actualizando...
                                </span>
                            </template>
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>