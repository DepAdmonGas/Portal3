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
            hora: false,
            gsuper: false,
            gpremium: false,
            gdiesel: false
        },
        productoTres: false,

        init(){

            if (!document.getElementById('modalCambioPrecio')) {
                return;
            }

            window.cambioprecio = this;
            this.productoTres = document.getElementById('container')?.dataset.productoTres === '1';
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

        formatearPrecio(valor) {
            const original = String(valor ?? '').replace(/,/g, '').replace(/[^\d.]/g, '');
            if (original === '') return '';

            const separador = original.indexOf('.');
            let entero = separador >= 0 ? original.slice(0, separador) : original;
            const decimales = separador >= 0
                ? original.slice(separador + 1).replace(/\./g, '').slice(0, 2)
                : null;

            entero = (entero || '0').replace(/^0+(?=\d)/, '');
            entero = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            return decimales === null ? entero : `${entero}.${decimales}`;
        },

        actualizarPrecio(campo, event) {
            const valor = this.formatearPrecio(event.target.value);
            this.form[campo] = valor;
            event.target.value = valor;
            this.errors[campo] = false;
        },

        precioSinSeparadores(valor) {
            const normalizado = String(valor ?? '').replace(/,/g, '').trim();
            return normalizado;
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

            ['gsuper', 'gpremium'].forEach(campo => {
                const precio = Number(this.precioSinSeparadores(this.form[campo]));
                if (!Number.isFinite(precio) || precio <= 0) {
                    this.errors[campo] = true;
                    valid = false;
                }
            });

            if (this.productoTres) {
                const diesel = Number(this.precioSinSeparadores(this.form.gdiesel));
                if (!Number.isFinite(diesel) || diesel <= 0) {
                    this.errors.gdiesel = true;
                    valid = false;
                }
            }

            return valid;
        },

    async guardar() {

        if (!this.validate()) {
            this.notify('error', 'Captura fecha y hora, y precios mayores a $0.00 para los productos habilitados.');
            return;
        }

    const payload = {
        
        fecha: this.form.fecha,
        hora: this.form.hora,
        gsuper: this.precioSinSeparadores(this.form.gsuper),
        gpremium: this.precioSinSeparadores(this.form.gpremium),
        gdiesel: this.precioSinSeparadores(this.form.gdiesel)
    };

    const url = '/sasisopa/cambio-precio/create';

    // createAction administra el loader y rechaza iniciar si loading ya está activo.
    // No activar loading aquí antes de invocarlo.
    const res = await this.createAction({
        url,
        data: payload,
        table: '#table-cambio-precio',
        notify: false
    });

    if (res?.success) {
        this.notify(
            res.web_sync ? 'success' : 'error',
            res.web_message || (res.web_sync
                ? 'Cambio de precio actualizado en la Página Web.'
                : 'El cambio se guardó en Portal3, pero no se sincronizó con la Página Web.')
        );
        this.modal.hide();
        this.resetModal();
    }

    },

    async eliminar(id){

        const name = `Cambio de precio #${id}`;

             const res = await this.deleteAction({
                url: '/sasisopa/cambio-precio/delete',
                id,
                name,
                table: '#table-cambio-precio'
            });

        if (res?.success) {
            this.notify(
                res.web_sync ? 'success' : 'error',
                res.web_message || (res.web_sync
                    ? 'El cambio también fue eliminado en la Página Web.'
                    : 'El cambio se eliminó en Portal3, pero no se sincronizó con la Página Web.')
            );
        }

    },

    }));
});
