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
        async createAction({ url, data = {}, table }) {

            if (this.loading) return;
            this.loading = true;

            try {
                const response = await axios.post(url, data);
                this.handleResponse(response, table);

            } catch (err) {
                this.notify('error', 'Error al crear');
            } finally {
                this.loading = false;
            }
        }

    }));

});