document.addEventListener('alpine:init', () => {

    Alpine.data('perfilPage', () => ({
        modalPerfil: null, modalInfoPersonal: null, modalFotoCropper: null, guardandoPerfil: false, guardandoInfoPersonal: false, guardandoFirma: false, eliminandoFirma: false, guardandoFotoPerfil: false, eliminandoFotoPerfil: false, errorFotoPerfil: '',
        imagenFotoCrop: null, urlFotoCrop: '', zoomFotoCrop: 1, xFotoCrop: 0, yFotoCrop: 0, escalaFotoCrop: 1, arrastrandoFotoCrop: false, ultimoPuntoFotoCrop: null, fotoPersonalizada: false,
        erroresPerfil: { email: '', telefono: '', usuario: '' },
        formPerfil: { email: '', telefono: '', usuario: '' },
        infoPersonal: { nombre: '', fecha_nacimiento: '', estado_civil: '', seguro_social: '', domicilio: '' },
        formInfoPersonal: { nombre: '', fecha_nacimiento: '', estado_civil: '', seguro_social: '', domicilio: '' },
        erroresInfoPersonal: { nombre: '', fecha_nacimiento: '', estado_civil: '', seguro_social: '', domicilio: '' },
        modoFirma: 'pad', editorFirmaAbierto: false, firmaPad: null, archivoFirma: null, vistaPreviaFirma: '', errorFirma: '',
        init() {
            this.modalPerfil = new bootstrap.Modal(this.$refs.modalPerfil);
            this.modalInfoPersonal = new bootstrap.Modal(this.$refs.modalInfoPersonal);
            this.modalFotoCropper = new bootstrap.Modal(this.$refs.modalFotoCropper);
            this.$refs.modalFotoCropper.addEventListener('shown.bs.modal', () => this.programarDibujoFotoCrop());
            const avatarPerfil = this.$root.querySelector('.profile-avatar');
            let rutaAvatarPerfil = '';
            try {
                rutaAvatarPerfil = new URL(avatarPerfil?.getAttribute('src') || '', window.location.href).pathname;
            } catch (_) {}
            const avatarPredeterminado = /\/assets\/images\/profile\/user-(?:m|f|general)\.jpg$/i.test(rutaAvatarPerfil);
            this.fotoPersonalizada = this.$root.dataset.profilePhoto === '1' && !avatarPredeterminado;
            // La fuente que realmente se muestra prevalece sobre un atributo antiguo de la vista.
            this.$root.dataset.profilePhoto = this.fotoPersonalizada ? '1' : '0';
            this.infoPersonal = {
                nombre: this.$root.dataset.profileName || '',
                fecha_nacimiento: this.$root.dataset.profileBirthdate || '',
                estado_civil: this.$root.dataset.profileMaritalStatus || '',
                seguro_social: this.$root.dataset.profileSocialSecurity || '',
                domicilio: this.$root.dataset.profileAddress || ''
            };
        },
        programarDibujoFotoCrop() {
            const dibujar = () => requestAnimationFrame(() => requestAnimationFrame(() => this.dibujarFotoCrop()));
            dibujar();
            window.setTimeout(dibujar, 100);
        },
        async aplicarFotoPerfil(url) {
            if (!url) return;
            const imagen = new Image();
            imagen.src = url;
            try {
                if (typeof imagen.decode === 'function') await imagen.decode();
                else await new Promise((resolve, reject) => { imagen.onload = resolve; imagen.onerror = reject; });
            } catch (_) {
                return;
            }
            document.querySelectorAll('.profile-avatar').forEach((avatar) => { avatar.src = url; });
        },
        abrirModalPerfil() {
            this.erroresPerfil = { email: '', telefono: '', usuario: '' };
            this.formPerfil = { email: this.$root.dataset.profileEmail || '', telefono: this.$root.dataset.profilePhone || '', usuario: this.$root.dataset.profileUsername || '' };
            this.modalPerfil.show();
        },
        abrirModalInfoPersonal() {
            this.erroresInfoPersonal = { nombre: '', fecha_nacimiento: '', estado_civil: '', seguro_social: '', domicilio: '' };
            this.formInfoPersonal = { ...this.infoPersonal };
            this.modalInfoPersonal.show();
        },
        fechaNacimientoTexto() {
            const valor = this.infoPersonal.fecha_nacimiento;
            if (!valor) return 'No registrado';
            const [anio, mes, dia] = valor.split('-').map(Number);
            if (!anio || !mes || !dia) return 'No registrado';
            return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(anio, mes - 1, dia));
        },
        async guardarInfoPersonal() {
            this.erroresInfoPersonal = { nombre: '', fecha_nacimiento: '', estado_civil: '', seguro_social: '', domicilio: '' };
            const nombre = this.formInfoPersonal.nombre.trim();
            if (!nombre) {
                this.erroresInfoPersonal.nombre = 'El nombre completo es obligatorio.';
                Notify.error(this.erroresInfoPersonal.nombre);
                return;
            }
            this.guardandoInfoPersonal = true;
            try {
                const { data } = await axios.post('/perfil/actualizar-informacion-personal', { ...this.formInfoPersonal, nombre });
                this.infoPersonal = { ...data.informacion };
                this.$root.dataset.profileName = this.infoPersonal.nombre;
                this.$root.dataset.profileBirthdate = this.infoPersonal.fecha_nacimiento || '';
                this.$root.dataset.profileMaritalStatus = this.infoPersonal.estado_civil || '';
                this.$root.dataset.profileSocialSecurity = this.infoPersonal.seguro_social || '';
                this.$root.dataset.profileAddress = this.infoPersonal.domicilio || '';
                this.modalInfoPersonal.hide();
                Notify.success(data.message || 'La información personal se actualizó correctamente.');
            } catch (error) {
                const data = error.response?.data || {};
                Object.keys(this.erroresInfoPersonal).forEach((key) => {
                    const message = data.errors?.[key];
                    if (message) this.erroresInfoPersonal[key] = Array.isArray(message) ? message[0] : message;
                });
                Notify.error(data.message || 'No fue posible actualizar la información personal.');
            } finally {
                this.guardandoInfoPersonal = false;
            }
        },
        async guardarPerfil() {
            this.erroresPerfil = { email: '', telefono: '', usuario: '' };
            const usuario = this.formPerfil.usuario.trim();
            if (!usuario) { this.erroresPerfil.usuario = 'El usuario es obligatorio.'; Notify.error(this.erroresPerfil.usuario); return; }
            if (usuario.length < 3 || usuario.length > 50 || !/^[\p{L}\p{N}._-]+$/u.test(usuario)) { this.erroresPerfil.usuario = 'Escribe un usuario válido de 3 a 50 caracteres.'; Notify.error(this.erroresPerfil.usuario); return; }
            if (this.formPerfil.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.formPerfil.email)) { this.erroresPerfil.email = 'Escribe un correo electrónico válido.'; Notify.error(this.erroresPerfil.email); return; }
            this.guardandoPerfil = true;
            try {
                const { data } = await axios.post('/perfil/actualizar-datos', { ...this.formPerfil, usuario });
                this.modalPerfil.hide(); Notify.success(data.message || 'Perfil actualizado correctamente.');
                if (data.logout_required) { await this.cerrarSesionYRedirigir(); return; }
                window.setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                const data = error.response?.data || {};
                Object.keys(this.erroresPerfil).forEach((key) => { const message = data.errors?.[key]; if (message) this.erroresPerfil[key] = Array.isArray(message) ? message[0] : message; });
                Notify.error(data.message || 'No fue posible actualizar el perfil.');
            } finally { this.guardandoPerfil = false; }
        },
        async actualizarFotoPerfil(event) {
            const foto = event.target.files?.[0] || null;
            this.errorFotoPerfil = '';
            if (!foto) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(foto.type) || foto.size > 5 * 1024 * 1024) {
                this.errorFotoPerfil = 'Selecciona una imagen JPG, PNG o WEBP de máximo 5 MB.';
                Notify.error(this.errorFotoPerfil);
                event.target.value = '';
                return;
            }

            const url = URL.createObjectURL(foto);
            const imagen = new Image();
            imagen.onload = () => {
                if (this.urlFotoCrop) URL.revokeObjectURL(this.urlFotoCrop);
                this.urlFotoCrop = url;
                this.imagenFotoCrop = imagen;
                this.zoomFotoCrop = 1;
                this.xFotoCrop = 0;
                this.yFotoCrop = 0;
                this.modalFotoCropper.show();
                this.$nextTick(() => this.programarDibujoFotoCrop());
            };
            imagen.onerror = () => {
                URL.revokeObjectURL(url);
                this.errorFotoPerfil = 'No fue posible abrir la imagen. Selecciona otro archivo.';
                Notify.error(this.errorFotoPerfil);
                event.target.value = '';
            };
            imagen.src = url;
        },
        dibujarFotoCrop() {
            const canvas = this.$refs.canvasFotoCrop;
            const imagen = this.imagenFotoCrop;
            if (!canvas || !imagen || !imagen.complete || !imagen.naturalWidth || !imagen.naturalHeight) return;
            const rect = canvas.getBoundingClientRect();
            const ancho = rect.width;
            const alto = rect.height;
            if (ancho < 1 || alto < 1) return;
            const escalaBase = Math.max(ancho / imagen.naturalWidth, alto / imagen.naturalHeight);
            const escala = escalaBase * this.zoomFotoCrop;
            const anchoImagen = imagen.naturalWidth * escala;
            const altoImagen = imagen.naturalHeight * escala;
            const xCentrado = (ancho - anchoImagen) / 2;
            const yCentrado = (alto - altoImagen) / 2;
            const x = Math.min(0, Math.max(ancho - anchoImagen, xCentrado + this.xFotoCrop));
            const y = Math.min(0, Math.max(alto - altoImagen, yCentrado + this.yFotoCrop));
            this.escalaFotoCrop = escala;
            this.xFotoCrop = x - xCentrado;
            this.yFotoCrop = y - yCentrado;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = Math.round(ancho * ratio);
            canvas.height = Math.round(alto * ratio);
            const contexto = canvas.getContext('2d');
            if (!contexto) return;
            contexto.setTransform(ratio, 0, 0, ratio, 0, 0);
            contexto.clearRect(0, 0, ancho, alto);
            contexto.drawImage(imagen, x, y, anchoImagen, altoImagen);
        },
        cambiarZoomFotoCrop() {
            if (!this.imagenFotoCrop) return;
            this.$nextTick(() => this.dibujarFotoCrop());
        },
        iniciarArrastreFotoCrop(event) {
            if (!this.imagenFotoCrop) return;
            this.arrastrandoFotoCrop = true;
            this.ultimoPuntoFotoCrop = { x: event.clientX, y: event.clientY };
            event.currentTarget.setPointerCapture?.(event.pointerId);
        },
        moverFotoCrop(event) {
            if (!this.arrastrandoFotoCrop || !this.ultimoPuntoFotoCrop) return;
            this.xFotoCrop += event.clientX - this.ultimoPuntoFotoCrop.x;
            this.yFotoCrop += event.clientY - this.ultimoPuntoFotoCrop.y;
            this.ultimoPuntoFotoCrop = { x: event.clientX, y: event.clientY };
            this.dibujarFotoCrop();
        },
        terminarArrastreFotoCrop() {
            this.arrastrandoFotoCrop = false;
            this.ultimoPuntoFotoCrop = null;
        },
        cerrarCropFoto() {
            this.modalFotoCropper?.hide();
            this.liberarFotoCrop();
        },
        liberarFotoCrop() {
            this.imagenFotoCrop = null;
            this.arrastrandoFotoCrop = false;
            if (this.urlFotoCrop) URL.revokeObjectURL(this.urlFotoCrop);
            this.urlFotoCrop = '';
            if (this.$refs.fotoPerfilInput) this.$refs.fotoPerfilInput.value = '';
        },
        async guardarFotoPerfilRecortada() {
            const imagen = this.imagenFotoCrop;
            const canvasVista = this.$refs.canvasFotoCrop;
            if (!imagen || !canvasVista) return;
            const rect = canvasVista.getBoundingClientRect();
            const ancho = rect.width;
            const alto = rect.height;
            if (ancho < 1 || alto < 1) return;
            const escala = Math.max(ancho / imagen.naturalWidth, alto / imagen.naturalHeight) * this.zoomFotoCrop;
            const anchoImagen = imagen.naturalWidth * escala;
            const altoImagen = imagen.naturalHeight * escala;
            const x = Math.min(0, Math.max(ancho - anchoImagen, (ancho - anchoImagen) / 2 + this.xFotoCrop));
            const y = Math.min(0, Math.max(alto - altoImagen, (alto - altoImagen) / 2 + this.yFotoCrop));
            const salida = document.createElement('canvas');
            const tamanoSalida = 384;
            salida.width = tamanoSalida;
            salida.height = tamanoSalida;
            const contexto = salida.getContext('2d');
            if (!contexto) return;
            contexto.fillStyle = '#ffffff';
            contexto.fillRect(0, 0, salida.width, salida.height);
            contexto.drawImage(imagen, x * tamanoSalida / ancho, y * tamanoSalida / alto, anchoImagen * tamanoSalida / ancho, altoImagen * tamanoSalida / alto);

            this.guardandoFotoPerfil = true;
            try {
                const blob = await new Promise((resolve) => salida.toBlob(resolve, 'image/webp', 0.82));
                if (!blob) throw new Error('No fue posible preparar el recorte de la foto.');
                const formulario = new FormData();
                formulario.append('foto', blob, 'foto-perfil.webp');
                const { data } = await axios.post('/perfil/actualizar-foto', formulario, { headers: { 'Content-Type': 'multipart/form-data' } });
                await this.aplicarFotoPerfil(data.foto);
                this.fotoPersonalizada = true;
                this.$root.dataset.profilePhoto = '1';
                this.cerrarCropFoto();
                Notify.success(data.message || 'La foto de perfil se actualizó correctamente.');
            } catch (error) {
                const data = error.response?.data || {};
                const campo = data.errors?.foto;
                this.errorFotoPerfil = Array.isArray(campo) ? campo[0] : (campo || data.message || error.message || 'No fue posible actualizar la foto de perfil.');
                Notify.error(data.message || this.errorFotoPerfil);
            } finally {
                this.guardandoFotoPerfil = false;
            }
        },
        async eliminarFotoPerfil() {
            if (this.eliminandoFotoPerfil || this.guardandoFotoPerfil) return;
            if (typeof Swal === 'undefined') {
                Notify.error('No fue posible abrir la confirmación para eliminar la foto.');
                return;
            }
            const confirmacion = await Swal.fire({
                icon: 'warning',
                title: '¿Eliminar foto personalizada?',
                text: 'Se mostrará nuevamente el avatar predeterminado de tu perfil.',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f56c6c'
            });
            if (!confirmacion.isConfirmed) return;

            this.eliminandoFotoPerfil = true;
            try {
                const { data } = await axios.post('/perfil/eliminar-foto', {});
                await this.aplicarFotoPerfil(data.foto);
                this.fotoPersonalizada = false;
                this.$root.dataset.profilePhoto = '0';
                Notify.success(data.message || 'La foto personalizada se eliminó correctamente.');
            } catch (error) {
                const data = error.response?.data || {};
                Notify.error(data.message || 'No fue posible eliminar la foto de perfil.');
            } finally {
                this.eliminandoFotoPerfil = false;
            }
        },
        async eliminarFirmaPerfil() {
            if (this.eliminandoFirma || this.guardandoFirma) return;
            if (typeof Swal === 'undefined') {
                Notify.error('No fue posible abrir la confirmación para eliminar la firma.');
                return;
            }
            const confirmacion = await Swal.fire({
                icon: 'warning',
                title: '¿Eliminar firma del perfil?',
                text: 'La firma dejará de estar asociada a tu perfil.',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f56c6c'
            });
            if (!confirmacion.isConfirmed) return;

            this.eliminandoFirma = true;
            try {
                const { data } = await axios.post('/perfil/eliminar-firma', {});
                Notify.success(data.message || 'La firma se eliminó del perfil correctamente.');
                window.setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                const data = error.response?.data || {};
                Notify.error(data.message || 'No fue posible eliminar la firma del perfil.');
            } finally {
                this.eliminandoFirma = false;
            }
        },
        alternarEditorFirma() {
            if (this.editorFirmaAbierto) {
                this.cancelarEdicionFirma();
                return;
            }
            this.limpiarFirmaForm();
            this.editorFirmaAbierto = true;
            this.$nextTick(() => this.iniciarFirmaPad());
        },
        cancelarEdicionFirma() {
            this.limpiarFirmaForm();
            this.editorFirmaAbierto = false;
        },
        seleccionarModoFirma(modo) { this.modoFirma = modo; this.errorFirma = ''; if (modo === 'pad') this.$nextTick(() => this.iniciarFirmaPad()); else { this.firmaPad?.off(); this.firmaPad = null; } },
        iniciarFirmaPad() {
            const canvas = this.$refs.canvasFirma;
            if (!canvas) return;
            if (typeof window.SignaturePad === 'undefined') {
                this.errorFirma = 'No se pudo cargar el panel de firma. Recarga la página e inténtalo de nuevo.';
                return;
            }
            const rect = canvas.getBoundingClientRect();
            if (rect.width < 1 || rect.height < 1) { window.requestAnimationFrame(() => this.iniciarFirmaPad()); return; }
            const trazos = this.firmaPad && !this.firmaPad.isEmpty() ? this.firmaPad.toData() : null;
            this.firmaPad?.off();
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = Math.round(rect.width * ratio); canvas.height = Math.round(rect.height * ratio);
            const context = canvas.getContext('2d');
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            this.firmaPad = new window.SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)', penColor: '#1f2937', minWidth: 1, maxWidth: 2.5 });
            if (trazos) this.firmaPad.fromData(trazos);
            this.errorFirma = '';
            if (!this._firmaResizeHandler) {
                this._firmaResizeHandler = () => {
                    if (this.editorFirmaAbierto && this.modoFirma === 'pad') this.$nextTick(() => this.iniciarFirmaPad());
                };
                window.addEventListener('resize', this._firmaResizeHandler);
            }
        },
        limpiarFirma() { this.firmaPad?.clear(); this.errorFirma = ''; },
        seleccionarArchivo(event) {
            this.archivoFirma = event.target.files?.[0] || null; this.errorFirma = '';
            if (!this.archivoFirma) { this.vistaPreviaFirma = ''; return; }
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(this.archivoFirma.type) || this.archivoFirma.size > 3 * 1024 * 1024) {
                this.errorFirma = 'Selecciona un PNG, JPG o WEBP de máximo 3 MB.'; event.target.value = ''; this.archivoFirma = null; this.vistaPreviaFirma = ''; return;
            }
            if (this.vistaPreviaFirma.startsWith('blob:')) URL.revokeObjectURL(this.vistaPreviaFirma);
            this.vistaPreviaFirma = URL.createObjectURL(this.archivoFirma);
        },
        limpiarFirmaForm() {
            this.firmaPad?.off(); this.firmaPad = null;
            if (this.$refs.canvasFirma) this.$refs.canvasFirma.getContext('2d')?.clearRect(0, 0, this.$refs.canvasFirma.width, this.$refs.canvasFirma.height);
            const input = document.getElementById('perfil-archivo-firma'); if (input) input.value = '';
            if (this.vistaPreviaFirma.startsWith('blob:')) URL.revokeObjectURL(this.vistaPreviaFirma);
            this.archivoFirma = null; this.vistaPreviaFirma = ''; this.errorFirma = ''; this.modoFirma = 'pad';
        },
        async guardarFirma() {
            this.errorFirma = ''; let response; this.guardandoFirma = true;
            try {
                if (this.modoFirma === 'archivo') {
                    if (!this.archivoFirma) { this.errorFirma = 'Selecciona una imagen de firma.'; return; }
                    const formData = new FormData(); formData.append('firma', this.archivoFirma);
                    response = await axios.post('/perfil/actualizar-firma', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
                } else {
                    if (!this.firmaPad || this.firmaPad.isEmpty()) { this.errorFirma = 'Dibuja la firma antes de guardar.'; return; }
                    response = await axios.post('/perfil/actualizar-firma', { firma: this.firmaPad.toDataURL('image/png') });
                }
                this.editorFirmaAbierto = false;
                this.limpiarFirmaForm();
                Notify.success(response.data.message || 'La firma se actualizó correctamente.'); window.setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                const data = error.response?.data || {}, field = data.errors?.firma;
                this.errorFirma = Array.isArray(field) ? field[0] : (field || data.message || 'No fue posible actualizar la firma.'); Notify.error(data.message || 'No fue posible actualizar la firma.');
            } finally { this.guardandoFirma = false; }
        },
        async cerrarSesionYRedirigir() { try { await axios.post('/logout', {}); } finally { window.location.assign('/login'); } }
    }));

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
                Notify.error(this.errores.password_actual);

                return false;
            }

            if (!this.form.password_nueva) {

                this.errores.password_nueva =
                    'Ingresa la nueva contraseña.';
                Notify.error(this.errores.password_nueva);

                return false;
            }

            const fortalezaNueva = this.evaluarFortaleza(this.form.password_nueva);
            if (!fortalezaNueva.segura) {

                this.errores.password_nueva = fortalezaNueva.requisitos[0] ||
                    'Elige una contraseña más segura.';
                Notify.error(this.errores.password_nueva);

                return false;
            }

            if (!this.form.password_confirmacion) {

                this.errores.password_confirmacion =
                    'Confirma la nueva contraseña.';
                Notify.error(this.errores.password_confirmacion);

                return false;
            }

            if (
                this.form.password_nueva !==
                this.form.password_confirmacion
            ) {

                this.errores.password_confirmacion =
                    'Las contraseñas no coinciden.';
                Notify.error(this.errores.password_confirmacion);

                return false;
            }

            if (
                this.form.password_actual ===
                this.form.password_nueva
            ) {

                this.errores.password_nueva =
                    'La nueva contraseña debe ser diferente a la actual.';
                Notify.error(this.errores.password_nueva);

                return false;
            }

            return true;
        },


        evaluarFortaleza(password) {
            const valor = String(password || '');
            if (!valor) return { score: 0, label: 'Muy débil', color: '#dc3545', textClass: 'text-danger', segura: false, requisitos: [] };

            const uppercase = /[A-Z]/.test(valor);
            const lowercase = /[a-z]/.test(valor);
            const number = /[0-9]/.test(valor);
            const special = /[!@#$%^&*()_+\-=\[\]{};:'",.<>/?\\|`~]/.test(valor);
            const requisitos = [];
            if (valor.length < 8) requisitos.push('La contraseña debe tener al menos 8 caracteres.');
            if (valor.length > 128) requisitos.push('La contraseña no puede superar 128 caracteres.');
            if (!uppercase) requisitos.push('Agrega al menos una letra mayúscula.');
            if (!lowercase) requisitos.push('Agrega al menos una letra minúscula.');
            if (!number) requisitos.push('Agrega al menos un número.');
            if (!special) requisitos.push('Agrega al menos un símbolo.');

            let score = Math.min(30, valor.length * 2);
            if (uppercase) score += 15;
            if (lowercase) score += 15;
            if (number) score += 15;
            if (special) score += 25;
            score = Math.min(100, score);

            const nivel = score <= 20 ? ['Muy débil', '#dc3545', 'text-danger']
                : score <= 40 ? ['Débil', '#fd7e14', 'text-warning']
                    : score <= 60 ? ['Media', '#ffc107', 'text-warning']
                        : score <= 80 ? ['Fuerte', '#198754', 'text-success']
                            : ['Muy fuerte', '#20c997', 'text-success'];

            return { score, label: nivel[0], color: nivel[1], textClass: nivel[2], segura: requisitos.length === 0, requisitos };
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

                this.modal.hide();
                Notify.success(response.data.message || 'Contraseña actualizada. Inicia sesión de nuevo.');
                try {
                    await axios.post('/logout', {});
                } finally {
                    window.location.assign('/login');
                }

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

                this.errorGeneral =
                    response?.message ??
                    'No fue posible actualizar la contraseña.';
                Notify.error(this.errorGeneral);

            } finally {

                this.guardando = false;
            }
        }

    }));


    Alpine.data('telegramToken', () => ({

        cargando: true,
        consultando: false,
        generando: false,
        revocando: false,
        enviando: false,

        estado: 'sin-registro',
        _verificadoShown: false,
        _primerCarga: true,
        _cargaExitosa: false,
        _solicitando: false,

        digits: [],

        expirado: false,
        segundos: 0,

        error: '',

        intervalo: null,


        init() {

            this.cargar();
            this._poll = setInterval(() => {
                if (this._cargaExitosa) this.cargar(false);
            }, 10000);
        },

        destroy() {
            if (this._poll) {
                clearInterval(this._poll);
            }
        },


        cargar(manual = false) {

            if (this._solicitando) return;
            this._solicitando = true;
            this.consultando = Boolean(manual);

            // The read-only status check stays silent; only a manual retry
            // should show the inline "Consultando" state.
            axios.post('/token-telegram/status', {}, {
                headers: { 'X-Portal3-Skip-Action-Loader': '1' }
            })
                .then(({ data }) => {

                    const d = data.data ?? {};
                    const estadoPrevio = this.estado;
                    this._cargaExitosa = true;
                    this.error = '';

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
                    if (manual || !this._cargaExitosa) {
                        this.error = 'No fue posible consultar el estado de Telegram. Puedes reintentar.';
                        if (!this._cargaExitosa) this.estado = 'error';
                    }
                    this._primerCarga = false;

                })
                .finally(() => {

                    this.cargando = false;
                    this.consultando = false;
                    this._solicitando = false;
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
                            ? 'Prueba enviada'
                            : 'No se pudo enviar la prueba',
                        text: data.message,
                        confirmButtonText: 'Aceptar'
                    });

                })
                .catch((error) => {

                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo enviar la prueba',
                        text: error.response?.data?.message ??
                            'No pudimos enviar el mensaje de prueba. Inténtalo de nuevo.',
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
