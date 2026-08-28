document.addEventListener('alpine:init', () => {

    Alpine.data('personal', () => ({

        //=========================================
        // DATOS
        //=========================================

        mode: 'create',

        loading: false,

        error: '',

        modal: null,

        puestos: window.__PUESTOS__ ?? [],

        id: null,
        nombre: '',
        telefono: '',
        email: '',
        fecha_ingreso: '',
        id_puesto: '',
        usuario: '',
        password: '',
        password_confirmacion: '',

        errors: {
            nombre: false,
            telefono: false,
            email: false,
            fecha_ingreso: false,
            id_puesto: false,
            usuario: false,
            password: false,
            password_confirmacion: false,
        },

        //=========================================
        // INIT
        //=========================================

        init() {

            if (!document.getElementById('modalPersonal')) {
                return;
            }

            window.personal = this;

            this.modal = new bootstrap.Modal(
                document.getElementById('modalPersonal')
            );

        },

        //=========================================
        // ABRIR AGREGAR
        //=========================================

        openCreate() {

            this.mode = 'create';

            this.resetModal();

            this.usuarioAleatorio();

            this.passwordAleatorio();

            this.modal.show();

        },

        //=========================================
        // ABRIR EDITAR
        //=========================================

        openEditar(row) {

            this.mode = 'edit';

            this.resetModal();

            this.id = row.id;
            this.nombre = row.nombre;
            this.telefono = row.telefono;
            this.email = row.email;
            this.fecha_ingreso = row.fecha_ingreso;
            this.id_puesto = row.id_puesto;
            this.usuario = row.usuario;
            this.password = row.password;

            this.modal.show();

        },

validate() {

    Object.keys(this.errors).forEach(k => this.errors[k] = false);

    let valid = true;

    const required = [
        'nombre',
        'email',
        'fecha_ingreso',
        'id_puesto',
        'usuario'
    ];

    required.forEach(field => {

        if (!this[field]) {

            this.errors[field] = true;

            valid = false;

        }

    });

    if (this.mode === 'create') {

        if (!this.password) {

            this.errors.password = true;

            valid = false;

        }

        if (!this.password_confirmacion) {

            this.errors.password_confirmacion = true;

            valid = false;

        }

        if (
            this.password &&
            this.password_confirmacion &&
            this.password !== this.password_confirmacion
        ) {

            this.notify(
                'error',
                'Las contraseñas no coinciden'
            );

            valid = false;

        }

    }

    return valid;

},

        //=========================================
        // GUARDAR
        //=========================================

async submit() {

    if (!this.validate()) {
        this.notify('error', 'Completa los campos obligatorios');
        return;
    }

    const payload = {
        id: this.id,
        nombre: this.nombre,
        telefono: this.telefono,
        email: this.email,
        fecha_ingreso: this.fecha_ingreso,
        id_puesto: this.id_puesto,
        usuario: this.usuario,
        password: this.password,
        password_confirmacion: this.password_confirmacion
    };

    const url = this.mode === 'create'
        ? '/personal/create'
        : '/personal/update';

    const res = await this.createAction({
        url,
        data: payload,
        table: '#table-personal'
    });

    if (res?.success) {
        this.modal.hide();
        this.resetModal();
    }

},

        //=========================================
        // LIMPIAR
        //=========================================

        resetModal() {

            this.id = null;

            this.nombre = '';
            this.telefono = '';
            this.email = '';
            this.fecha_ingreso = '';
            this.id_puesto = '';
            this.usuario = '';
            this.password = '';
            this.password_confirmacion = '';
            this.error = '';

            Object.keys(this.errors).forEach(key => {
                this.errors[key] = false;
            });

        },

        //=========================================
        // USUARIO ALEATORIO
        //=========================================

        usuarioAleatorio() {

            const chars =
                'abcdefghijklmnopqrstuvwxyz0123456789';

            this.usuario = '';

            for (let i = 0; i < 8; i++) {

                this.usuario += chars.charAt(
                    Math.floor(Math.random() * chars.length)
                );

            }

        },

        //=========================================
        // PASSWORD ALEATORIO
        //=========================================

        passwordAleatorio() {

            const chars =
                'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@#$%';

            let pwd = '';

            for (let i = 0; i < 10; i++) {

                pwd += chars.charAt(
                    Math.floor(Math.random() * chars.length)
                );

            }

            this.password = pwd;
            this.password_confirmacion = pwd;

        },

        //=========================================
        // ELIMINAR
        //=========================================

        async delete(id) {

            await this.deleteAction({
                url: '/personal/delete',
                id,
                name: 'Personal',
                table: '#table-personal'
            });

        }

    }));

});