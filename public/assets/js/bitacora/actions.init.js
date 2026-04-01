document.addEventListener('alpine:init', () => {

    Alpine.data('aditivoForm', () => ({

        init() {
            window.aditivoInstance = this;
        },
        
        // MODO
        mode: 'create',
        id: null,

        // CAMPOS
        litros: '',
        producto: '',
        galones: 0,
        fecha: '',
        no_factura: '',

        // VALIDACIÓN
        errors: {
            litros: false,
            producto: false,
            fecha: false
        },

        // CALCULAR GALONES
        calcular() {

            if (!this.producto || !this.litros) {
                this.galones = 0;
                return;
            }

            let litros = parseFloat(this.litros);
            let PL = 20000;
            let PG = 0;
            let resultado = 0;

            switch (this.producto) {

                case 'G SUPER':
                    PG = 1;

                    if (litros === 30000) {
                        resultado = 2;
                    } else if (litros <= 10000) {
                        resultado = 0.5;
                    } else {
                        resultado = (litros / PL) * PG;
                    }
                break;

                case 'G PREMIUM':
                case 'G DIESEL':
                    PG = 2;
                    resultado = (litros / PL) * PG;
                break;
            }

            this.galones = Number(resultado.toFixed(2));
        },

         openEdit(data) {

            this.mode = 'edit';
            this.id = data.id;

            this.litros = data.litros ?? '';
            this.producto = data.producto ?? '';
            this.galones = data.galones ?? 0;

            // FIX FECHA
            this.fecha = data.fecha ? data.fecha.split('T')[0] : '';

            this.no_factura = data.no_factura ?? '';

            const modalEl = document.getElementById('nuevo');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        },

        // VALIDAR FORM
        validate() {

            if (this.mode === 'create') {

                this.errors.litros = !this.litros;
                this.errors.producto = !this.producto;
                this.errors.fecha = !this.fecha;

                return !this.errors.litros &&
                       !this.errors.producto &&
                       !this.errors.fecha;
            }

            // EDIT → solo factura
            return !!this.no_factura;
        },

        // 🔹 RESET
        resetForm() {
            this.mode = 'create';
            this.id = null;

            this.litros = '';
            this.producto = '';
            this.galones = 0;
            this.fecha = '';
            this.no_factura = '';

            this.errors = {
                litros: false,
                producto: false,
                fecha: false
            };
        },

        resetModal(){

            const modalEl = document.getElementById('nuevo');

                    // evento al cerrar completamente
                    modalEl.addEventListener('hidden.bs.modal', () => {
                        this.resetForm();
                        document.body.focus(); // opcional (mejora accesibilidad)
                    }, { once: true });

                    // quitar foco ANTES de cerrar
                    if (document.activeElement) {
                        document.activeElement.blur();
                    }

                    const modal = bootstrap.Modal.getInstance(modalEl);

                    if (modal) {
                        modal.hide();
                    }

        },

        // SUBMIT
        async submit() {

        if (!this.validate()) {
            this.notify('error', 'Completa los campos obligatorios');
            return;
        }

        let payload = {};
        let url = '';

        if (this.mode === 'create') {

            url = '/bitacora-aditivo/create';

            payload = {
                litros: this.litros,
                producto: this.producto,
                galones: this.galones,
                fecha: this.fecha,
                no_factura: this.no_factura
            };

        } else {

            url = '/bitacora-aditivo/update';

            payload = {
                id: this.id,
                no_factura: this.no_factura
            };
        }

        try {

            const res = await this.createAction({
                url,
                data: payload,
                table: '#table-aditivo'
            });

            if (res && res.success) {
                
                this.updateInventario();
                this.resetModal();                

            }

        } catch (error) {
            this.notify('error', 'Error al guardar');
        }
    },
        updateInventario() {

        axios.get('/bitacora-aditivo/totalInventario')
            .then(res => {

                const gas = document.getElementById('inv-gasolina');
                const die = document.getElementById('inv-diesel');

                if (gas) gas.textContent = res.data.gasolina + ' galones';
                if (die) die.textContent = res.data.diesel  + ' galones';

            })
            .catch(() => {
                this.notify('error', 'Error al actualizar inventario');
            });
    }

    }));

});