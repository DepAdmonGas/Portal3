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

});