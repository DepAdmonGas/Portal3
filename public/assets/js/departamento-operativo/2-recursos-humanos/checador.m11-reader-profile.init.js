document.addEventListener('alpine:init', () => {
    Alpine.data('m11ReaderProfile', () => ({
        profiles: [],
        profileId: '',
        username: '',
        password: '',
        passwordConfirm: '',
        modal: null,
        rootElement: null,
        loading: false,
        error: '',
        fieldError: '',
        success: '',

        init() {
            this.rootElement = this.$el;
            try {
                const parsed = JSON.parse(this.$el.dataset.readerProfiles || '[]');
                this.profiles = Array.isArray(parsed) ? parsed.filter(profile =>
                    Number.isSafeInteger(Number(profile?.id)) && Number(profile.id) > 0
                    && typeof profile?.usuario === 'string'
                ) : [];
            } catch (_) {
                this.error = 'No fue posible cargar el perfil del lector.';
                return;
            }

            const modalElement = this.$el.querySelector('#m11-reader-profile-modal');
            if (modalElement) {
                this.modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                modalElement.addEventListener('hidden.bs.modal', () => this.resetModal());
            }
        },

        openEdit(button) {
            if (this.loading || !this.modal) return;
            const id = Number(button?.dataset.profileId);
            const profile = this.profiles.find(item => Number(item.id) === id);
            if (!profile || String(button.dataset.username ?? '') !== profile.usuario) {
                this.error = 'No fue posible identificar el perfil. Actualiza la página e inténtalo de nuevo.';
                return;
            }
            this.profileId = String(profile.id);
            this.username = profile.usuario;
            this.password = '';
            this.passwordConfirm = '';
            this.fieldError = '';
            this.error = '';
            this.modal.show();
        },

        clearMessages() {
            this.fieldError = '';
            this.error = '';
        },

        async save(event) {
            const form = event.currentTarget;
            if (this.loading) return;
            this.clearMessages();
            this.username = String(this.username ?? '').trim();
            const id = Number(this.profileId);
            const profileExists = this.profiles.some(item => Number(item.id) === id);

            if (!profileExists) {
                this.error = 'El perfil seleccionado ya no está disponible. Actualiza la página.';
                return;
            }
            if (!this.username || this.username.length > 120) {
                this.fieldError = 'El usuario es obligatorio y no puede exceder 120 caracteres.';
                form.elements.username?.focus();
                return;
            }
            if (this.password.length > 512) {
                this.fieldError = 'La contraseña nueva no puede exceder 512 caracteres.';
                form.elements.new_password?.focus();
                return;
            }
            if (this.password !== this.passwordConfirm) {
                this.fieldError = 'La confirmación de la nueva contraseña no coincide.';
                form.elements.new_password_confirm?.focus();
                return;
            }
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            this.loading = true;
            const submitButton = form.querySelector('[type="submit"]');
            const cancelButton = form.querySelector('[data-bs-dismiss="modal"]');
            if (submitButton) submitButton.disabled = true;
            if (cancelButton) cancelButton.disabled = true;
            try {
                const response = await axios.post(this.rootElement.dataset.saveUrl, new FormData(form), {
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': form.elements._csrf_token?.value || '',
                    },
                });
                const body = response.data || {};
                const saved = body.profile || {};
                if (body.ok !== true || Number(saved.id) !== id || saved.username !== this.username) {
                    throw { response: { status: response.status, data: body } };
                }
                const existing = this.profiles.find(item => Number(item.id) === id);
                if (!existing) throw new Error('El servidor confirmó el cambio, pero no se encontró el perfil para actualizar.');
                existing.usuario = saved.username;
                this.success = body.message || 'Perfil del lector guardado y confirmado.';
                this.password = '';
                this.passwordConfirm = '';
                this.modal?.hide();
            } catch (failure) {
                const status = Number(failure?.response?.status || 0);
                const body = failure?.response?.data || {};
                const messages = {
                    401: 'Tu sesión expiró. Inicia sesión nuevamente.',
                    403: 'No tienes permiso para editar el perfil del lector de esta localidad.',
                    409: 'El perfil cambió o dejó de estar activo. Actualiza la página antes de intentar de nuevo.',
                    419: 'La protección de sesión venció. Actualiza la página antes de intentar de nuevo.',
                    422: body.message || 'Revisa el usuario y la confirmación de contraseña.',
                };
                this.error = body.message || messages[status] || (status >= 500
                    ? 'El servidor no pudo confirmar el cambio. Consulta el perfil antes de volver a intentar.'
                    : (failure?.message || 'No se pudo guardar el perfil del lector.'));
            } finally {
                this.loading = false;
                if (submitButton) submitButton.disabled = false;
                if (cancelButton) cancelButton.disabled = false;
            }
        },

        resetModal() {
            if (this.loading) return;
            this.profileId = '';
            this.username = '';
            this.password = '';
            this.passwordConfirm = '';
            this.fieldError = '';
            this.error = '';
        },
    }));
});
