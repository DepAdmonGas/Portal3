document.addEventListener('alpine:init', () => {
    Alpine.data('cambioPrecio', () => ({

        modal: null,
        loading:false,

        form:{
            fecha:'',
            hora:'',
            gsuper:'',
            gpremium:'',
            gdiesel:''

        },

        errors: {
            fecha: false,
            hora: false
        },

        init(){

            if (!document.getElementById('modalCambioPrecio')) {
                return;
            }

            window.cambioprecio = this;
            this.modal = new bootstrap.Modal(
                document.getElementById('modalCambioPrecio')
            );
        },

        openCreate(){

        this.resetModal();
        this.modal.show();

        },

        resetModal() {

        this.form = {
            fecha:'',
            hora:'',
            gsuper:'',
            gpremium:'',
            gdiesel:''

        }   

         Object.keys(this.errors).forEach(key => {
                this.errors[key] = false;
            });

        },

        validate() {
            Object.keys(this.errors).forEach(k => this.errors[k] = false);
            let valid = true;

            if (!this.form.fecha) {
            this.errors.fecha = true;
            valid = false;
            }

            if (!this.form.hora) {
            this.errors.hora = true;
            valid = false;
            }            

            return valid;
        },

    async guardar() {

        if (!this.validate()) {
            this.notify('error', 'Completa los campos obligatorios');
            return;
        }

    const payload = {
        
        fecha: this.form.fecha,
        hora: this.form.hora,
        gsuper: this.form.gsuper,
        gpremium: this.form.gpremium,
        gdiesel: this.form.gdiesel
    };

    const url = '/sasisopa/cambio-precio/create';

    const res = await this.createAction({
        url,
        data: payload,
        table: '#table-cambio-precio'
    });

    if (res?.success) {
        this.paginaWeb(res.id);
        this.modal.hide();
        this.resetModal();
    }

    },

    async paginaWeb(idReporte) {

    try {

        const params = new URLSearchParams({
            idEstacion: document.getElementById('container').dataset.estacionId || this.estacion,
            idReporte: idReporte,
            GSUPER: this.form.gsuper,
            GPREMIUM: this.form.gpremium,
            GDIESEL: this.form.gdiesel,
            Fecha: this.form.fecha,
            Hora: this.form.hora,
            TOKEN: '28102020'
        });

        const response = await fetch(
            `https://www.admongas.com.mx/app/api/postCambioPrecio.php?${params.toString()}`
        );

        const json = await response.json();

        if (json.status === 'success') {

            this.notify(
                'success',
                'Cambio de precio actualizado en la Página Web.'
            );

        }

    } catch (error) {

        console.error(error);

        this.notify(
            'error',
            'Error al procesar la solicitud.'
        );

    }

},

    async eliminar(id){

        name = id;

             const res = await this.deleteAction({
                url: '/sasisopa/cambio-precio/delete',
                id,
                name,
                table: '#table-cambio-precio'
            });

        if (res?.success) {
            await this.eliminarWeb(id);
        }

    },

    async eliminarWeb(id) {

        try {

            const response = await fetch(
                'https://www.admongas.com.mx/app/api/postEliminarCambioPrecio.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        idReporte: id
                    })
                }
            );

            const json = await response.json();

            if (json.success) {

                this.notify(
                    'success',
                    'El cambio de precio fue eliminado'
                );

                return true;

            }

            this.notify(
                'error',
                'Error eliminando en servidor remoto'
            );

            return false;

        } catch (e) {

            this.notify(
                'error',
                'Error al conectar con el servidor remoto'
            );

            return false;

        }

    }

    }));
});