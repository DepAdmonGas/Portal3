document.addEventListener('alpine:init', () => {
    Alpine.data('listaAceitesComponent', () => ({
        items: [],
        tempValues: {},
        creando: false,
        editandoConcepto: null,

        init() {
            const data = window.__ACEITES_DATA__ || [];
            this.items = data;
            this.items.forEach(item => {
                this.tempValues[item.id] = {
                    id_aceite: item.id_aceite || '',
                    concepto: item.concepto || '',
                    piezas: item.piezas || 0,
                    precio: item.precio || 0,
                };
            });
        },

        editarConcepto(id) {
            this.editandoConcepto = id;
            this.$nextTick(() => {
                const input = this.$refs.conceptoInput;
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },

        guardarConcepto(id) {
            this.editandoConcepto = null;
            const item = this.items.find(i => i.id === id);
            const valor = this.tempValues[id].concepto;
            if (item && item.concepto === valor) return;
            this.guardar(id, 'concepto', valor);
        },

        cancelarEdicionConcepto(id) {
            const item = this.items.find(i => i.id === id);
            if (item) {
                this.tempValues[id].concepto = item.concepto || '';
            }
            this.editandoConcepto = null;
        },

        async guardar(id, campo, valor) {
            try {
                const res = await fetch('/departamento-operativo/corporativo/lista-aceites/guardar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, campo, valor }),
                });
                const json = await res.json();
                if (json.success) {
                    const item = this.items.find(i => i.id === id);
                    if (item) item[campo] = valor;
                    const nombreCampo = { concepto: 'Concepto', piezas: 'Piezas', precio: 'Precio' }[campo] || campo;
                    Notify['success'](nombreCampo + ' actualizado correctamente.');
                } else {
                    Notify['error'](json.message || 'Error al guardar');
                }
            } catch (err) {
                Notify['error']('Error de conexión al guardar');
            }
        },

        eliminarFila(id) {
            this.items = this.items.filter(i => i.id !== id);
            delete this.tempValues[id];
        },

        async nuevoAceite() {
            this.creando = true;
            try {
                const res = await fetch('/departamento-operativo/corporativo/lista-aceites/nuevo', {
                    method: 'POST',
                });
                const json = await res.json();
                if (json.success && json.data) {
                    const nuevo = json.data;
                    this.items.push(nuevo);
                    this.tempValues[nuevo.id] = {
                        id_aceite: nuevo.id_aceite || '',
                        concepto: nuevo.concepto || '',
                        piezas: nuevo.piezas || 0,
                        precio: nuevo.precio || 0,
                    };
                    this.$nextTick(() => {
                        const el = document.getElementById('finalDePagina');
                        if (el) el.scrollIntoView({ behavior: 'smooth' });
                    });
                    Notify['success']('Fila agregada al final de la tabla.');
                } else {
                    Notify['error'](json.message || 'Error al crear');
                }
            } catch (err) {
                Notify['error']('Error de conexión al crear');
            } finally {
                this.creando = false;
            }
        },
    }));
});
