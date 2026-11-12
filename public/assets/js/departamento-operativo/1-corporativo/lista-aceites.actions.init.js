document.addEventListener('alpine:init', () => {
    Alpine.data('listaAceitesComponent', () => ({
        items: [],
        tempValues: {},
        creando: false,
        editandoConcepto: null,
        selectedRowId: null,

        formatInputNumber(value) {
            if (value === null || value === undefined || value === '') return '';
            const normalized = String(value).replace(/,/g, '').replace(/[^0-9.]/g, '');
            const parts = normalized.split('.');
            const integer = parts.shift() || '';
            const decimals = parts.join('');
            const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return normalized.includes('.') ? grouped + '.' + decimals : grouped;
        },

        formatNumericInput(event, row, field) {
            const input = event.target;
            const raw = input.value;
            const cursor = input.selectionStart ?? raw.length;
            const allowDecimals = field === 'precio';
            const numericPattern = allowDecimals ? /[^0-9.]/g : /[^0-9]/g;
            const digitsBeforeCursor = raw.slice(0, cursor).replace(/,/g, '').replace(numericPattern, '').length;
            const normalized = raw.replace(/,/g, '').replace(numericPattern, '');
            const parts = normalized.split('.');
            const integer = parts.shift() || '';
            const decimals = parts.join('');
            const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const formatted = normalized.includes('.') ? grouped + '.' + decimals : grouped;

            row[field] = normalized;
            input.value = formatted;

            let nextCursor = 0;
            let numericChars = 0;
            while (nextCursor < formatted.length && numericChars < digitsBeforeCursor) {
                if (/[0-9.]/.test(formatted[nextCursor])) numericChars++;
                nextCursor++;
            }
            input.setSelectionRange(nextCursor, nextCursor);
        },

        async init() {
            try {
                const res = await fetch('/departamento-operativo/corporativo/lista-aceites/data');
                const json = await res.json();
                if (json.success) {
                    this.items = json.data;
                }
            } catch (e) {
                console.error('Error al cargar aceites:', e);
            }
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

        seleccionarFila(id) {
            this.selectedRowId = id;
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
            const esCantidad = campo === 'piezas' || campo === 'precio';
            const edicionEnLinea = esCantidad || campo === 'concepto';
            if (esCantidad) {
                const limpio = String(valor ?? '').replace(/,/g, '').trim();
                if (limpio === '' || !Number.isFinite(Number(limpio))) {
                    Notify['error']('Ingresa una cantidad válida.');
                    return;
                }
                valor = campo === 'piezas' ? parseInt(limpio, 10) : Number(limpio);
            }
            try {
                const headers = { 'Content-Type': 'application/json' };
                if (edicionEnLinea) headers['X-Portal3-Skip-Action-Loader'] = '1';
                const res = await axios.post(
                    '/departamento-operativo/corporativo/lista-aceites/guardar',
                    { id, campo, valor },
                    { headers }
                );
                const json = res.data;
                if (json.success) {
                    const item = this.items.find(i => i.id === id);
                    if (item) item[campo] = valor;
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
            if (this.selectedRowId === id) this.selectedRowId = null;
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
