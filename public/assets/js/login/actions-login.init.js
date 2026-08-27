function loginForm() {

    return {

        usuario: '',
        password: '',

        errors: {
            usuario: '',
            password: ''
        },

        message: '',
        type: '',
        loading: false,


        validar() {

            this.errors.usuario = '';
            this.errors.password = '';

            let valido = true;

            if (!this.usuario.trim()) {

                this.errors.usuario =
                    'El usuario es obligatorio.';

                valido = false;
            }

            if (!this.password.trim()) {

                this.errors.password =
                    'La contraseña es obligatoria.';

                valido = false;
            }

            return valido;
        },


        async login() {

            if (this.loading) {
                return;
            }

            this.message = '';
            this.type = '';

            if (!this.validar()) {
                return;
            }

            this.loading = true;

            try {

                const res = await axios.post(
                    '/login/acceso',
                    {
                        usuario: this.usuario.trim(),
                        password: this.password
                    }
                );

                this.message =
                    res.data.message ?? '';

                this.type =
                    res.data.type ?? 'error';


                if (this.type === 'success') {

                    setTimeout(() => {

                        window.location.href =
                            '/home';

                    }, 800);

                    return;
                }

                this.loading = false;

            } catch (error) {

                this.message =
                    error.response?.data?.message
                    ?? 'Ocurrió un error al iniciar sesión.';

                this.type = 'error';

                this.loading = false;
            }

        }

    };

}