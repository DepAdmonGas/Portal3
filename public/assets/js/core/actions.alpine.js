document.addEventListener('alpine:init', () => {

    Alpine.data('actions', () => ({

        loading: false,

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
    }

    }));

});