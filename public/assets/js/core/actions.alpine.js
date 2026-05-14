document.addEventListener('alpine:init', () => {

    Alpine.data('actions', () => ({

        loading: false,
        _modalSelect2Bound: {},
        _modalSelect2Watched: {},

        // ALERTA
        showAlert(icon, title, text) {
            Swal.fire({
                icon,
                title,
                text,
                timer: 2000,
                showConfirmButton: false
            });
        },

        // NOTIFICACIÓN
        notify(type, message) {
            Notify[type](message);
        },

        // RESPUESTA GLOBAL
        handleResponse(response, table = null) {
            const { success, message } = response.data;

            this.showAlert(
                success ? 'success' : 'error',
                success ? 'Correcto' : 'Error',
                message
            );

            this.notify(success ? 'success' : 'error', message);

            if (success && table) {
                $(table).DataTable().ajax.reload(null, false);
            }
        },

        // DELETE GLOBAL
        async deleteAction({ url, id, name, table }) {

            if (this.loading) return;

            const result = await Swal.fire({
                title: '¿Eliminar Registro?',
                text: `El registro: ${name} será eliminado`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            });

            if (!result.isConfirmed) return;

            this.loading = true;

            try {
                const response = await axios.post(url, { id });
                this.handleResponse(response, table);

                return response.data;

            } catch (err) {

                const mensaje =
                    err.response?.data?.message ||
                    'Error al eliminar';

                this.showAlert('error', 'Error', mensaje);
                this.notify('error', mensaje);

            } finally {
                this.loading = false;
            }
        },

        // BAJA GLOBAL

        async bajaAction({ url, id, name, table }) {

            if (this.loading) return;

            const result = await Swal.fire({
                title: '¿Dar de Baja Registro?',
                text: `El registro: ${name} será dado de baja`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, baja',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            });

            if (!result.isConfirmed) return;

            this.loading = true;

            try {
                const response = await axios.post(url, { id });
                this.handleResponse(response, table);

                return response.data;

            } catch (err) {

                const mensaje =
                    err.response?.data?.message ||
                    'Error al dar de baja';

                this.showAlert('error', 'Error', mensaje);
                this.notify('error', mensaje);

            } finally {
                this.loading = false;
            }
        },

        // EDIT
        goTo(url) {
            window.location.href = url;
        },

        // CREATE
        async createAction({
    url,
    data = {},
    table = null,
    method = 'POST',
    headers = {},
    onSuccess = null,
    onError = null
}) {

    if (this.loading) return;
    this.loading = true;

    try {

        let config = {
            method,
            url,
            data,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...headers
            }
        };

        // FORM DATA
        if (data instanceof FormData) {
            config.headers['Content-Type'] = 'multipart/form-data';
        }

        const response = await axios(config);

        const res = response.data;

        if (!res) {
            throw new Error('Respuesta vacía del servidor');
        }

        // MANEJO GLOBAL
        if (res.success) {

            if (table) {
                $(table).DataTable().ajax.reload(null, false);
            }

            if (res.message) {
                this.notify('success', res.message);
            }

            if (typeof onSuccess === 'function') {
                onSuccess(res);
            }

        } else {

            this.notify('error', res.message || 'Error');

            if (typeof onError === 'function') {
                onError(res);
            }
        }

        return res;

    } catch (err) {

        console.error('ERROR AXIOS:', err);

        const mensaje =
            err.response?.data?.message ||
            err.message ||
            'Error en la solicitud';

        this.notify('error', mensaje);

        if (typeof onError === 'function') {
            onError({ success: false, message: mensaje });
        }

        return {
            success: false,
            message: mensaje
        };

    } finally {
        this.loading = false;
    }
},
        download(tipo, archivo) {

        if (!archivo) {
            this.notify('error', 'Archivo no disponible');
            return;
        }

        const url = `/download?tipo=${tipo}&file=${encodeURIComponent(archivo)}`;

        window.open(url, '_blank');
    },

    getModalSelect2Elements({
        selectRef,
        wrapperRef = null,
        modalRef = null
    }) {
        const selectEl = this.$refs?.[selectRef] || null;
        const wrapperEl = wrapperRef
            ? (this.$refs?.[wrapperRef] || null)
            : (selectEl?.parentElement || null);
        const modalEl = modalRef
            ? (this.$refs?.[modalRef] || document.getElementById(modalRef))
            : null;

        return {
            selectEl,
            wrapperEl,
            modalEl
        };
    },

    destroyModalSelect2({
        selectRef,
        wrapperRef = null,
        namespace = 'modalSelect2'
    }) {
        const { selectEl, wrapperEl } = this.getModalSelect2Elements({
            selectRef,
            wrapperRef
        });

        if (wrapperEl) {
            wrapperEl.classList.add('is-select2-pending');
        }

        if (!selectEl) {
            return;
        }

        const $select = $(selectEl);

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.off(`.${namespace}`);
            $select.select2('destroy');
        }
    },

    watchModalSelect2({
        selectRef,
        model
    }) {
        const watchKey = `${selectRef}:${model}`;

        if (this._modalSelect2Watched[watchKey]) {
            return;
        }

        this.$watch(model, value => {
            const { selectEl } = this.getModalSelect2Elements({ selectRef });

            if (!selectEl) {
                return;
            }

            const $select = $(selectEl);

            if (!$select.hasClass('select2-hidden-accessible')) {
                return;
            }

            const nextValue = value || '';
            if (($select.val() || '') !== nextValue) {
                $select.val(nextValue).trigger('change.select2');
            }
        });

        this._modalSelect2Watched[watchKey] = true;
    },

    initModalSelect2({
        selectRef,
        wrapperRef = null,
        modalRef = null,
        model,
        options = {},
        namespace = 'modalSelect2'
    }) {
        const { selectEl, wrapperEl, modalEl } = this.getModalSelect2Elements({
            selectRef,
            wrapperRef,
            modalRef
        });

        if (!selectEl) {
            return;
        }

        const $select = $(selectEl);
        const $dropdownParent = wrapperEl ? $(wrapperEl) : $(modalEl || selectEl.parentElement);

        this.destroyModalSelect2({
            selectRef,
            wrapperRef,
            namespace
        });

        $select.select2({
            dropdownParent: $dropdownParent,
            width: '100%',
            ...options
        });

        const $container = $select.next('.select2-container');
        $container.css('width', '100%');

        $select.off(`change.${namespace}`).on(`change.${namespace}`, event => {
            this[model] = $(event.target).val() || '';
        });

        $select.off(`select2:open.${namespace}`).on(`select2:open.${namespace}`, () => {
            window.dispatchEvent(new Event('resize'));
        });

        $select.val(this[model] || '').trigger('change.select2');

        if (wrapperEl) {
            requestAnimationFrame(() => {
                wrapperEl.classList.remove('is-select2-pending');
            });
        }
    },

    bindModalSelect2({
        modalRef,
        selectRef,
        wrapperRef = null,
        model,
        options = {},
        namespace = 'modalSelect2',
        onShown = null
    }) {
        const bindKey = `${modalRef}:${selectRef}:${namespace}`;

        if (this._modalSelect2Bound[bindKey]) {
            return;
        }

        const { modalEl } = this.getModalSelect2Elements({
            selectRef,
            wrapperRef,
            modalRef
        });

        if (!modalEl) {
            return;
        }

        this.watchModalSelect2({
            selectRef,
            model
        });

        modalEl.addEventListener('shown.bs.modal', () => {
            if (typeof onShown === 'function') {
                const shouldContinue = onShown.call(this);

                if (shouldContinue === false) {
                    return;
                }
            }

            this.$nextTick(() => {
                this.initModalSelect2({
                    selectRef,
                    wrapperRef,
                    modalRef,
                    model,
                    options,
                    namespace
                });
            });
        });

        modalEl.addEventListener('hide.bs.modal', () => {
            const activeElement = document.activeElement;

            if (activeElement && modalEl.contains(activeElement) && typeof activeElement.blur === 'function') {
                activeElement.blur();
            }
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            this.destroyModalSelect2({
                selectRef,
                wrapperRef,
                namespace
            });
        });

        this._modalSelect2Bound[bindKey] = true;
    }

    }));

});
