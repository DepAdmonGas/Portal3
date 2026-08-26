<!-- Pantalla de carga -->
<div class="loader-admongas">
    <img src="<?= asset('images/logos/logo-empresaMov.gif') ?>" alt="Cargando..." class="logo-loader-admongas" />
</div>

<div id="main-wrapper" class="auth-customizer-none">
    <div class="position-relative overflow-hidden radial-gradient min-vh-100 w-100">
        <div class="position-relative z-index-5">

            <div class="row">
                <div class="col-xl-5 col-xxl-4">

                    <div class="authentication-login min-vh-100 bg-body row justify-content-center align-items-center p-4">
                        <div class="auth-max-width col-sm-8 col-md-6 col-xl-7 px-4">

                            <a class="logo-img d-block py-4 w-100">
                                <img src="<?= asset('images/logos/Logo.png') ?>" class="dark-logo w-75" alt="Logo Admongas" />
                                <img src="<?= asset('images/logos/Logo-dark.png') ?>" class="light-logo w-75" alt="Logo Admongas Dark" />
                            </a>

                            <div class="mb-4">

                                <h1
                                    class="fw-bolder mb-2">
                                    Bienvenido a Portal3
                                </h1>

                                <p
                                    class="text-muted mb-0 fs-4">
                                    Ingresa tus credenciales para acceder a tu cuenta.
                                </p>

                            </div>

                            <form
                                x-data="loginForm()"
                                @submit.prevent="login"
                                novalidate>

                                <div class="mb-4">

                                    <label
                                        for="usuario"
                                        class="form-label">
                                        Usuario
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control form-control-lg"
                                        id="usuario"
                                        x-model="usuario"
                                        :class="{ 'is-invalid': errors.usuario }"
                                        @input="errors.usuario = ''"
                                        :disabled="loading"
                                        autocomplete="username"
                                        placeholder="Ingresa tu usuario">

                                    <div
                                        class="invalid-feedback"
                                        x-show="errors.usuario"
                                        x-text="errors.usuario"></div>

                                </div>


                                <div class="mb-3">

                                    <label
                                        for="password"
                                        class="form-label">
                                        Contraseña
                                    </label>

                                    <input
                                        type="password"
                                        class="form-control form-control-lg"
                                        id="password"
                                        x-model="password"
                                        :class="{ 'is-invalid': errors.password }"
                                        @input="errors.password = ''"
                                        :disabled="loading"
                                        autocomplete="current-password"
                                        placeholder="Ingresa tu contraseña">

                                    <div
                                        class="invalid-feedback"
                                        x-show="errors.password"
                                        x-text="errors.password"></div>

                                </div>


                                <div
                                    class="d-flex align-items-center justify-content-between mb-3">

                                    <div class="form-check">

                                        <input
                                            class="form-check-input primary"
                                            type="checkbox"
                                            id="flexCheckChecked"
                                            checked>

                                        <label
                                            class="form-check-label text-dark fs-3"
                                            for="flexCheckChecked">
                                            Recordar este usuario
                                        </label>

                                    </div>

                                </div>


                                <button
                                    type="submit"
                                    class="btn btn-primary w-100 py-3 rounded-2 fw-semibold"
                                    :disabled="loading">

                                    <template x-if="!loading">

                                        <span>
                                            Iniciar sesión
                                        </span>

                                    </template>

                                    <template x-if="loading">

                                        <span
                                            class="d-inline-flex align-items-center gap-2">

                                            <span
                                                class="spinner-border spinner-border-sm"></span>

                                            Iniciando sesión...

                                        </span>

                                    </template>

                                </button>


                                <div
                                    x-show="message"
                                    x-transition
                                    class="alert mt-4"
                                    :class="{
                                    'alert-success text-success':
                                        type === 'success',

                                    'alert-danger text-danger':
                                        type === 'error',

                                    'alert-warning text-warning':
                                        type === 'warning'
                                }"
                                    role="alert">

                                    <div
                                        class="d-flex align-items-center gap-2">

                                        <i
                                            class="ti"
                                            :class="{
                                            'ti-circle-check':
                                                type === 'success',

                                            'ti-alert-circle':
                                                type === 'error',

                                            'ti-alert-triangle':
                                                type === 'warning'
                                        }"></i>

                                        <span
                                            x-text="message"></span>

                                    </div>

                                </div>

                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-7 col-xxl-8 d-none d-xl-block position-relative">

                    <div
                        class="position-relative vh-100 overflow-hidden">

                        <img
                            src="<?= asset('images/desing-photo-estaciones/img-login.png') ?>"
                            alt="Estación de servicio"
                            class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover">

                        <div
                            class="position-absolute top-0 start-0 w-100 h-100"
                            style="
                            background:
                                linear-gradient(
                                    180deg,
                                    rgba(0,0,0,.05) 0%,
                                    rgba(0,0,0,.10) 45%,
                                    rgba(0,0,0,.55) 100%
                                );
                        "></div>

                        <div
                            class="position-absolute start-0 bottom-0 p-5 text-white"
                            style="max-width: 720px;">

                            <div class="mb-3">

                                <span
                                    class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 px-3 py-2">
                                    Portal3
                                </span>

                            </div>

                            <h2
                                class="text-white fw-bolder mb-3">
                                Gestión eficiente para tu estación de servicio
                            </h2>

                            <p
                                class="text-white mb-0 fs-4"
                                style="opacity: .9;">
                                Centraliza operaciones, seguimiento y administración
                                desde una sola plataforma.
                            </p>

                        </div>

                    </div>

                </div>

            </div>
        </div>
    </div>
</div>