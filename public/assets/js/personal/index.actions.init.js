document.addEventListener('alpine:init', () => {

    Alpine.data('personal', () => ({

        //=========================================
        // DATOS
        //=========================================

        mode: 'create',

        loading: false,

        error: '',
        mensajeErrorUsuario: '',

        modal: null,

        puestos: (() => {
            const element = document.getElementById('personal-data');
            if (!element) return [];
            try { return JSON.parse(element.textContent || '[]'); } catch (_) { return []; }
        })(),

        id: null,
        nombre: '',
        telefono: '',
        email: '',
        fecha_ingreso: '',
        id_puesto: '',
        usuario: '',
        password: '',
        passwordOriginal: '',
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
            this.passwordOriginal = this.password;

            this.modal.show();

        },

validate() {

    Object.keys(this.errors).forEach(k => this.errors[k] = false);
    this.error = '';
    this.mensajeErrorUsuario = '';

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

    const errorUsuario = this.validarNombreUsuario(this.usuario);
    if (errorUsuario) {
        this.errors.usuario = true;
        this.mensajeErrorUsuario = errorUsuario;
        valid = false;
    }

    const cambiarPassword = this.mode === 'create'
        || this.password !== this.passwordOriginal;

    if (cambiarPassword) {

        if (!this.password) {

            this.errors.password = true;

            valid = false;

        }

        const fortaleza = this.evaluarFortalezaPassword(this.password);
        if (!fortaleza.segura || fortaleza.label !== 'Muy fuerte') {
            this.errors.password = true;
            this.error = fortaleza.requisitos.join(' ')
                || 'La contraseña debe alcanzar el nivel Muy fuerte.';
            valid = false;
        }

        if (!this.password_confirmacion) {

            this.errors.password_confirmacion = true;
            this.error = 'Confirma la contraseña.';

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
            this.errors.password_confirmacion = true;
            this.error = 'Las contraseñas no coinciden.';

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
        usuario: this.usuario.trim(),
        password: this.password,
        password_confirmacion: this.password_confirmacion,
        module: document.getElementById('container').dataset.moduleStationKey || 'sasisopa'
    };

    const url = this.mode === 'create'
        ? '/personal/create'
        : '/personal/update';

    const res = await this.createAction({
        url,
        data: payload,
        table: '#table-personal',
        onError: respuesta => {
            const errorUsuario = respuesta?.errors?.usuario;
            if (errorUsuario) {
                this.errors.usuario = true;
                this.mensajeErrorUsuario = Array.isArray(errorUsuario)
                    ? errorUsuario.join(' ')
                    : String(errorUsuario);
            }
        }
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
            this.passwordOriginal = '';
            this.password_confirmacion = '';
            this.error = '';
            this.mensajeErrorUsuario = '';

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

            this.errors.usuario = false;
            this.mensajeErrorUsuario = '';

        },

        //=========================================
        // PASSWORD ALEATORIO
        //=========================================

        passwordAleatorio() {

            const grupos = [
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz',
                '0123456789',
                '!@#$%^&*()_+-='
            ];
            const caracteres = grupos.join('');
            const aleatorio = maximo => {
                const valor = new Uint32Array(1);
                window.crypto.getRandomValues(valor);
                return valor[0] % maximo;
            };

            const valores = grupos.map(grupo => grupo[aleatorio(grupo.length)]);
            while (valores.length < 16) {
                valores.push(caracteres[aleatorio(caracteres.length)]);
            }

            for (let i = valores.length - 1; i > 0; i--) {
                const j = aleatorio(i + 1);
                [valores[i], valores[j]] = [valores[j], valores[i]];
            }

            const pwd = valores.join('');

            this.password = pwd;
            this.password_confirmacion = pwd;
            this.errors.password = false;
            this.errors.password_confirmacion = false;
            this.error = '';

        },

        evaluarFortalezaPassword(password) {
            const valor = String(password || '');
            if (!valor) {
                return {
                    score: 0,
                    label: 'Muy débil',
                    color: '#dc3545',
                    textClass: 'text-danger',
                    segura: false,
                    requisitos: []
                };
            }

            const mayuscula = /[A-Z]/.test(valor);
            const minuscula = /[a-z]/.test(valor);
            const numero = /[0-9]/.test(valor);
            const simbolo = /[!@#$%^&*()_+\-=\[\]{};:'",.<>/?\\|`~]/.test(valor);
            const requisitos = [];

            if (valor.length < 8) requisitos.push('La contraseña debe tener al menos 8 caracteres.');
            if (valor.length > 128) requisitos.push('La contraseña no puede superar 128 caracteres.');
            if (!mayuscula) requisitos.push('Agrega al menos una letra mayúscula.');
            if (!minuscula) requisitos.push('Agrega al menos una letra minúscula.');
            if (!numero) requisitos.push('Agrega al menos un número.');
            if (!simbolo) requisitos.push('Agrega al menos un símbolo.');

            let score = Math.min(30, valor.length * 2);
            if (mayuscula) score += 15;
            if (minuscula) score += 15;
            if (numero) score += 15;
            if (simbolo) score += 25;
            score = Math.min(100, score);

            const nivel = score <= 20 ? ['Muy débil', '#dc3545', 'text-danger']
                : score <= 40 ? ['Débil', '#fd7e14', 'text-warning']
                    : score <= 60 ? ['Media', '#ffc107', 'text-warning']
                        : score <= 80 ? ['Fuerte', '#198754', 'text-success']
                            : ['Muy fuerte', '#20c997', 'text-success'];

            return {
                score,
                label: nivel[0],
                color: nivel[1],
                textClass: nivel[2],
                segura: requisitos.length === 0,
                requisitos
            };
        },

        validarNombreUsuario(usuario) {
            const valor = String(usuario || '').trim();
            const longitud = [...valor].length;

            if (longitud < 3 || longitud > 50 || !/^[\p{L}\p{N}._-]+$/u.test(valor)) {
                return 'Escribe un usuario de 3 a 50 caracteres; utiliza letras, números, punto, guion o guion bajo.';
            }

            return '';
        },

        //=========================================
        // ELIMINAR
        //=========================================

        async delete(id) {

            await this.deleteAction({
                url: '/personal/delete',
                id,
                name: 'Personal',
                table: '#table-personal',
                data: {
                    module: document.getElementById('container').dataset.moduleStationKey || 'sasisopa'
                }
            });

        }

    }));

});
