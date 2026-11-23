document.addEventListener('alpine:init', () => { 
    Alpine.data('inventarioForm', () => ({

        getBaseUrl() {
            const el = document.getElementById('container');
            return el && el.dataset.baseUrl ? el.dataset.baseUrl : '/bitacora-aditivo';
        },

        init() {
            window.inventarioInstance = this;
        },

        gasolina: '',
        diesel: '',
        errors: { gasolina: false, diesel: false },

        formatearCantidad(event, campo) {
            const input = event.target;
            const original = input.value;
            const posicion = input.selectionStart ?? original.length;
            const digitosAntes = original.slice(0, posicion).replace(/\D/g, '').length;
            let normalizado = original.replace(/,/g, '').replace(/[^0-9.]/g, '');
            const partes = normalizado.split('.');
            normalizado = partes[0] + (partes.length > 1 ? '.' + partes.slice(1).join('') : '');
            if (!normalizado) {
                this[campo] = '';
                input.value = '';
                return;
            }
            const [entero = '', ...decimales] = normalizado.split('.');
            const formateado = (entero.replace(/^0+(?=\d)/, '') || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',')
                + (normalizado.includes('.') ? '.' + decimales.join('') : '');
            this[campo] = formateado;
            input.value = formateado;
            let vistos = 0;
            let nuevaPosicion = input.value.length;
            for (let i = 0; i < input.value.length; i += 1) {
                if (/\d/.test(input.value[i])) vistos += 1;
                if (digitosAntes > 0 && vistos >= digitosAntes) {
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

        validate() {
            const hayGasolina = this.valorCantidad(this.gasolina) > 0;
            const hayDiesel = this.valorCantidad(this.diesel) > 0;
            this.errors.gasolina = !hayGasolina && !hayDiesel;
            this.errors.diesel = !hayGasolina && !hayDiesel;
            if (this.errors.gasolina || this.errors.diesel) {
                this.notify('error', 'Completa los campos obligatorios.');
                return false;
            }
            return true;
        },
        resetForm() {
            this.gasolina = '';
            this.diesel = '';
            this.errors = { gasolina: false, diesel: false };
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
        async submit() {
            if (!this.validate()) return;

            let payload = {
                gasolina: this.valorCantidad(this.gasolina),
                diesel: this.valorCantidad(this.diesel)
            };

            let url = this.getBaseUrl() + '/create-inventario';

            try {
                
                const res = await this.createAction({
                url,
                data: payload,
                table: '#table-aditivo-inventario'
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

                if (gas) gas.textContent = Number(res.data.gasolina || 0).toLocaleString('en-US') + ' Galones';
                if (die) die.textContent = Number(res.data.diesel || 0).toLocaleString('en-US') + ' Galones';

            })
            .catch(() => {
                this.notify('error', 'Error al actualizar inventario');
            });
    }

    }));

});
