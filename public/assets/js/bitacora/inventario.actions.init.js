document.addEventListener('alpine:init', () => { 
    Alpine.data('inventarioForm', () => ({

        init() {
            window.inventarioInstance = this;
        },

        gasolina: '',
        diesel: '',

        validate() {
            // Retorna true si al menos uno tiene valor
            if (!this.gasolina && !this.diesel) {
                this.notify('error', 'Debes ingresar al menos un aditivo');
                return false;
            }
            return true;
        },
        resetForm() {
            this.gasolina = '';
            this.diesel = '';
        },
        async submit() {
            if (!this.validate()) return;

            let payload = {
                gasolina: this.gasolina || 0,
                diesel: this.diesel || 0
            };

            let url = '/bitacora-aditivo/create-inventario';

            try {
                
                const res = await this.createAction({
                url,
                data: payload,
                table: '#table-aditivo-inventario'
                });

                if (res && res.success) {

                const modalEl = document.getElementById('nuevo');

                //quitar foco (error aria-hidden)
                document.activeElement.blur();

                //IMPORTANTE: esperar a que cierre
                modalEl.addEventListener('hidden.bs.modal', () => {

                    this.resetForm();

                    // ACTUALIZAR INVENTARIO AQUÍ
                    this.updateInventario();

                }, { once: true });

                const modal = bootstrap.Modal.getInstance(modalEl);

                if (modal) {
                    modal.hide();
                }

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