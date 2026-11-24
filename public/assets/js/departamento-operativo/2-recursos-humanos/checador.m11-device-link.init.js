document.addEventListener('alpine:init', () => {
    Alpine.data('m11DeviceLink', () => ({
        devices: [],
        tokens: {},
        loadingDevice: null,
        copyingDevice: null,
        error: '',
        success: '',

        init() {
            try {
                const parsed = JSON.parse(this.$el.dataset.devices || '[]');
                this.devices = Array.isArray(parsed) ? parsed.filter(item =>
                    Number.isSafeInteger(Number(item?.device?.id)) && Number(item.device.id) > 0
                ) : [];
            } catch (_) {
                this.error = 'No fue posible preparar la información de vinculación.';
            }
        },

        async issue(deviceId) {
            const id = Number(deviceId);
            if (!Number.isSafeInteger(id) || id < 1 || this.loadingDevice !== null) return;
            this.clearMessages();
            // A previous plaintext token may have been revoked by a retry or a lost response.
            delete this.tokens[id];
            this.loadingDevice = id;
            try {
                const response = await axios.post(this.$el.dataset.generateUrl, {
                    workplace_id: Number(this.$el.dataset.workplaceId),
                    device_id: id,
                }, {
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.$el.dataset.csrfToken || '',
                    },
                });
                const body = response.data || {};
                if (body.ok !== true || typeof body.token !== 'string' || !/^[0-9a-f]{64}$/.test(body.token)
                    || Number(body.device_id) !== id || typeof body.expires_at_utc !== 'string') {
                    throw { response: { status: response.status, data: body } };
                }
                this.tokens[id] = { value: body.token, expiresAt: body.expires_at_utc };
                const device = this.devices.find(item => Number(item.device.id) === id);
                if (device) {
                    if (device.link_status !== 'LINKED') device.link_status = 'TOKEN_PENDING';
                    device.pending_token_expires_at_utc = body.expires_at_utc;
                }
                this.success = body.message || 'Token generado. Cópialo ahora; no volverá a mostrarse.';
            } catch (failure) {
                this.setRequestError(failure);
            } finally {
                this.loadingDevice = null;
            }
        },

        async revoke(deviceId) {
            const id = Number(deviceId);
            if (!Number.isSafeInteger(id) || id < 1 || this.loadingDevice !== null) return;
            this.clearMessages();
            delete this.tokens[id];
            this.loadingDevice = id;
            try {
                const response = await axios.post(this.$el.dataset.revokeUrl, {
                    workplace_id: Number(this.$el.dataset.workplaceId),
                    device_id: id,
                }, {
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.$el.dataset.csrfToken || '',
                    },
                });
                const body = response.data || {};
                if (body.ok !== true) throw { response: { status: response.status, data: body } };
                const device = this.devices.find(item => Number(item.device.id) === id);
                if (device) {
                    device.pending_token_expires_at_utc = null;
                    device.biometric_grant_status = 'REVOKED_OR_MISSING';
                    device.link_status = 'NOT_LINKED';
                }
                this.success = body.message || 'Se revocaron la vinculación, el acceso biométrico y los tokens pendientes.';
            } catch (failure) {
                this.setRequestError(failure);
            } finally {
                this.loadingDevice = null;
            }
        },

        async copy(deviceId) {
            const id = Number(deviceId);
            const token = this.tokens[id]?.value;
            if (typeof token !== 'string' || token.length !== 64 || this.copyingDevice !== null) return;
            this.clearMessages();
            this.copyingDevice = id;
            try {
                if (!navigator.clipboard?.writeText) throw new Error('clipboard_unavailable');
                await navigator.clipboard.writeText(token);
                this.success = 'Token copiado. Está disponible sólo en esta pantalla y vence a la hora indicada.';
            } catch (_) {
                this.error = 'No fue posible copiar automáticamente. Selecciona el token y cópialo antes de que expire.';
            } finally {
                this.copyingDevice = null;
            }
        },

        clearMessages() {
            this.error = '';
            this.success = '';
        },

        setRequestError(failure) {
            const status = Number(failure?.response?.status || 0);
            const body = failure?.response?.data || {};
            const messages = {
                401: 'Tu sesión expiró. Inicia sesión nuevamente.',
                403: 'No tienes autorización para administrar la vinculación de esta estación.',
                404: 'El dispositivo ya no pertenece a la estación seleccionada.',
                409: body.message || 'La estación, el dispositivo o sus permisos biométricos requieren revisión.',
                419: 'La protección de sesión venció. Actualiza la página e inténtalo de nuevo.',
                422: body.message || 'La solicitud de vinculación no es válida.',
                426: body.message || 'Abre Configuración mediante HTTPS para vincular el dispositivo.',
                503: body.message || 'La base compartida no pudo confirmar la operación.',
            };
            this.error = body.message || messages[status] || (failure?.message === 'clipboard_unavailable'
                ? 'El navegador no permite copiar en este contexto.'
                : 'No fue posible confirmar la operación.');
        },
    }));
});
