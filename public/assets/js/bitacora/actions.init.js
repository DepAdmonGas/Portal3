document.addEventListener('alpine:init', () => {

    Alpine.data('aditivoForm', () => ({

        getBaseUrl() {
            const el = document.getElementById('container');
            return el && el.dataset.baseUrl ? el.dataset.baseUrl : '/bitacora-aditivo';
        },

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

        formatearCantidad(event, campo) {
            const input = event.target;
            const valorOriginal = input.value;
            const posicion = input.selectionStart ?? valorOriginal.length;
            const digitosAntes = valorOriginal.slice(0, posicion).replace(/\D/g, '').length;
            let normalizado = valorOriginal.replace(/,/g, '').replace(/[^0-9.]/g, '');
            const partes = normalizado.split('.');
            normalizado = partes[0] + (partes.length > 1 ? '.' + partes.slice(1).join('') : '');

            if (!normalizado) {
                this[campo] = '';
                input.value = '';
                return;
            }

            const [entero = '', ...decimales] = normalizado.split('.');
            const enteroAgrupado = (entero.replace(/^0+(?=\d)/, '') || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const formateado = enteroAgrupado + (normalizado.includes('.') ? '.' + decimales.join('') : '');
            this[campo] = formateado;
            input.value = formateado;

            let vistos = 0;
            let nuevaPosicion = input.value.length;
            for (let i = 0; i < input.value.length; i += 1) {
                if (/\d/.test(input.value[i])) vistos += 1;
                if (vistos >= digitosAntes && digitosAntes > 0) {
                    nuevaPosicion = i + 1;
                    break;
                }
            }
            input.setSelectionRange(nuevaPosicion, nuevaPosicion);
        },

        valorCantidad(valor) {
            const numero = Number(String(valor ?? '').replace(/,/g, '').trim());
            return Number.isFinite(numero) ? numero : 0;
        },

        formatearCantidadExistente(valor) {
            if (valor === null || valor === undefined || valor === '') return '';
            const numero = Number(String(valor).replace(/,/g, ''));
            return Number.isFinite(numero) ? numero.toLocaleString('en-US', { maximumFractionDigits: 20 }) : '';
        },

        // VALIDACIÓN
        errors: {
            litros: false,
            producto: false,
            fecha: false,
            no_factura: false
        },

        // CALCULAR GALONES
        calcular() {

            if (!this.producto || !this.litros) {
                this.galones = 0;
                return;
            }

            let litros = this.valorCantidad(this.litros);
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

            this.galones = Number(resultado.toFixed(2)).toLocaleString('en-US', { maximumFractionDigits: 2 });
        },

         openEdit(data) {

            this.mode = 'edit';
            this.id = data.id;
            this.errors = { litros: false, producto: false, fecha: false, no_factura: false };

            this.litros = this.formatearCantidadExistente(data.litros ?? '');
            this.producto = data.producto ?? '';
            this.galones = this.formatearCantidadExistente(data.galones ?? 0);

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

                this.errors.litros = this.valorCantidad(this.litros) <= 0;
                this.errors.producto = !this.producto;
                this.errors.fecha = !this.fecha;
                this.errors.no_factura = !String(this.no_factura ?? '').trim();

                return !this.errors.litros &&
                       !this.errors.producto &&
                       !this.errors.fecha &&
                       !this.errors.no_factura;
            }

            // EDIT → solo factura
            this.errors.no_factura = !String(this.no_factura ?? '').trim();
            return !this.errors.no_factura;
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
                fecha: false,
                no_factura: false
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
            this.notify('error', 'Completa los campos obligatorios.');
            return;
        }

        let payload = {};
        let url = '';

        if (this.mode === 'create') {

            url = this.getBaseUrl() + '/create';

            payload = {
                litros: this.valorCantidad(this.litros),
                producto: this.producto,
                galones: this.galones,
                fecha: this.fecha,
                no_factura: this.no_factura
            };

        } else {

            url = this.getBaseUrl() + '/update';

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

        axios.get(this.getBaseUrl() + '/totalInventario')
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
