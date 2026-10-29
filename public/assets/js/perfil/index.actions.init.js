document.addEventListener('alpine:init', () => {

    Alpine.data('cambiarPassword', () => ({

        modal: null,

        guardando: false,

        mostrarActual: false,
        mostrarNueva: false,
        mostrarConfirmacion: false,

        errorGeneral: '',

        errores: {
            password_actual: '',
            password_nueva: '',
            password_confirmacion: ''
        },

        form: {
            password_actual: '',
            password_nueva: '',
            password_confirmacion: ''
        },


        init() {

            this.modal = new bootstrap.Modal(
                this.$refs.modal,
                {
                    backdrop: 'static',
                    keyboard: false
                }
            );

            this.$refs.modal.addEventListener(
                'hidden.bs.modal',
                () => {
                    this.limpiar();
                }
            );
        },


        abrirModal() {

            this.limpiar();

            this.modal.show();

            this.$nextTick(() => {

                const input = document.getElementById(
                    'password_actual'
                );

                input?.focus();
            });
        },


        limpiarErrores() {

            this.errorGeneral = '';

            this.errores = {
                password_actual: '',
                password_nueva: '',
                password_confirmacion: ''
            };
        },


        limpiar() {

            this.form = {
                password_actual: '',
                password_nueva: '',
                password_confirmacion: ''
            };

            this.mostrarActual = false;
            this.mostrarNueva = false;
            this.mostrarConfirmacion = false;

            this.guardando = false;

            this.limpiarErrores();
        },


        validar() {

            this.limpiarErrores();

            if (!this.form.password_actual) {

                this.errores.password_actual =
                    'Ingresa tu contraseña actual.';

                return false;
            }

            if (!this.form.password_nueva) {

                this.errores.password_nueva =
                    'Ingresa la nueva contraseña.';

                return false;
            }

            if (this.form.password_nueva.length < 8) {

                this.errores.password_nueva =
                    'La contraseña debe contener al menos 8 caracteres.';

                return false;
            }

            if (!this.form.password_confirmacion) {

                this.errores.password_confirmacion =
                    'Confirma la nueva contraseña.';

                return false;
            }

            if (
                this.form.password_nueva !==
                this.form.password_confirmacion
            ) {

                this.errores.password_confirmacion =
                    'Las contraseñas no coinciden.';

                return false;
            }

            if (
                this.form.password_actual ===
                this.form.password_nueva
            ) {

                this.errores.password_nueva =
                    'La nueva contraseña debe ser diferente a la actual.';

                return false;
            }

            return true;
        },


        async guardar() {

            if (!this.validar()) {
                return;
            }

            this.guardando = true;

            this.limpiarErrores();

            try {

                const response = await axios.post(
                    '/perfil/cambiar-password',
                    {
                        password_actual:
                            this.form.password_actual,

                        password_nueva:
                            this.form.password_nueva,

                        password_confirmacion:
                            this.form.password_confirmacion
                    }
                );

                console.log(response)

                this.modal.hide();

                await Swal.fire({
                    icon: 'success',
                    title: 'Contraseña actualizada',
                    text:
                        response.data.message ??
                        'Tu contraseña fue actualizada correctamente.',
                    confirmButtonText: 'Aceptar'
                });

            } catch (error) {

                const response = error.response?.data;

                if (response?.errors) {

                    Object.entries(response.errors)
                        .forEach(([campo, mensajes]) => {

                            if (
                                Object.prototype.hasOwnProperty.call(
                                    this.errores,
                                    campo
                                )
                            ) {
                                this.errores[campo] =
                                    Array.isArray(mensajes)
                                        ? mensajes[0]
                                        : mensajes;
                            }
                        });
                }

                console.log(error)

                this.errorGeneral =
                    response?.message ??
                    'No fue posible actualizar la contraseña.';

            } finally {

                this.guardando = false;
            }
        }

    }));


    Alpine.data('telegramToken', () => ({

        cargando: true,
        generando: false,
        revocando: false,
        enviando: false,

        estado: 'sin-registro',
        _verificadoShown: false,
        _primerCarga: true,

        digits: [],

        expirado: false,
        segundos: 0,

        error: '',

        intervalo: null,


        init() {

            this.cargar();
            this._poll = setInterval(() => {
                this.cargar();
            }, 2500);
        },

        destroy() {
            if (this._poll) {
                clearInterval(this._poll);
            }
        },


        cargar() {

            this.error = '';

            // This endpoint is polled every 2.5 seconds; exclude the read-only
            // status check from the global action loader to avoid flashing it.
            axios.post('/token-telegram/status', {}, {
                headers: { 'X-Portal3-Skip-Action-Loader': '1' }
            })
                .then(({ data }) => {

                    const d = data.data ?? {};
                    const estadoPrevio = this.estado;

                    if (d.verified) {

                        if (
                            estadoPrevio !== 'verificado' &&
                            !this._primerCarga &&
                            !this._verificadoShown
                        ) {
                            this._verificadoShown = true;
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Telegram verificado',
                                    text: 'Tu cuenta ha sido vinculada correctamente.',
                                    timer: 2500,
                                    showConfirmButton: false
                                });
                            }
                        }

                        this.estado = 'verificado';
                        this.digits = [];
                        this.limpiarContador();

                    } else if (d.registered && d.token) {

                        if (this.estado !== 'pendiente') {
                            this.estado = 'pendiente';
                            this.digits = String(d.token).split('');
                            this.iniciarContador(
                                d.expired ? 0 : (d.remaining_seconds || 0)
                            );
                        } else {
                            this.digits = String(d.token).split('');
                            this.segundos = d.expired ? 0 : (Math.floor(d.remaining_seconds || 0));
                        }

                    } else {

                        if (this.estado !== 'sin-registro') {
                            this.estado = 'sin-registro';
                            this.digits = [];
                            this.limpiarContador();
                        }

                    }

                    this._primerCarga = false;

                })
                .catch(() => {

                    if (this.estado !== 'error') {
                        this.estado = 'error';
                        this.error =
                            'No fue posible consultar el estado de Telegram.';
                    }

                    this._primerCarga = false;

                })
                .finally(() => {

                    this.cargando = false;
                });
        },


        generar() {

            if (this.generando) {
                return;
            }

            this.generando = true;
            this.error = '';

            axios.post('/token-telegram/generate')
                .then(({ data }) => {

                    const d = data.data ?? {};

                    this.estado = 'pendiente';
                    this.digits = d.digits || String(d.token || '').split('');
                    this.iniciarContador(d.remaining_seconds ?? 120);

                })
                .catch((error) => {

                    this.error =
                        error.response?.data?.message ??
                        'No fue posible generar el token.';

                })
                .finally(() => {

                    this.generando = false;
                });
        },


        revocar() {

            if (this.revocando) {
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: '¿Deseas revocar el acceso a tu dispositivo móvil?',
                text: 'Dejarás de recibir las notificaciones internas de Portal3 en tu Telegram.',
                showCancelButton: true,
                confirmButtonText: 'Sí, revocar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {

                if (!result.isConfirmed) {
                    return;
                }

                this.revocando = true;

                axios.post('/token-telegram/revoke')
                    .then(() => {

                        Swal.fire({
                            icon: 'success',
                            title: 'Acceso revocado',
                            text: 'El acceso a tu dispositivo fue revocado correctamente.',
                            confirmButtonText: 'Aceptar'
                        });

                        this.cargar();

                    })
                    .catch((error) => {

                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.response?.data?.message ??
                                'No fue posible revocar el acceso.',
                            confirmButtonText: 'Aceptar'
                        });

                    })
                    .finally(() => {

                        this.revocando = false;
                    });
            });
        },


        enviarPrueba() {

            if (this.enviando) {
                return;
            }

            this.enviando = true;
            this.error = '';

            axios.post('/token-telegram/test-notification')
                .then(({ data }) => {

                    Swal.fire({
                        icon: data.success ? 'success' : 'error',
                        title: data.success
                            ? 'Mensaje enviado'
                            : 'No se pudo enviar el mensaje',
                        text: data.message,
                        confirmButtonText: 'Aceptar'
                    });

                })
                .catch((error) => {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.response?.data?.message ??
                            'No fue posible enviar el mensaje.',
                        confirmButtonText: 'Aceptar'
                    });

                })
                .finally(() => {

                    this.enviando = false;
                });
        },


        iniciarContador(segundos) {

            this.limpiarContador();

            this.segundos = Math.max(0, Math.floor(segundos || 0));

            this.expirado = this.segundos <= 0;

            if (this.expirado) {
                return;
            }

            this.intervalo = setInterval(() => {

                if (this.segundos > 0) {

                    this.segundos--;

                } else {

                    this.limpiarContador();
                    this.expirado = true;
                }

            }, 1000);

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Código de verificación generado',
                    text: 'Podrás generar un nuevo código cuando el tiempo restante llegue a 0:00.',
                    timer: 3200,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true,
                    background: '#fff3cd',
                    color: '#856404',
                    iconColor: '#ffc107'
                });
            }
        },


        limpiarContador() {

            if (this.intervalo) {

                clearInterval(this.intervalo);
                this.intervalo = null;
            }
        },


        formatearTiempo() {

            const minutos = Math.floor(this.segundos / 60);
            const segundos = this.segundos % 60;

            return minutos + ':' + String(segundos).padStart(2, '0');
        }

    }));

});
