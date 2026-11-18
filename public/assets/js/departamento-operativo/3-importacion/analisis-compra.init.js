document.addEventListener('alpine:init', () => {

    Alpine.data('analisisCompraComponent', () => ({

        idUsuario: 0,
        idEstacion: 0,
        idYear: 0,
        idMes: 0,
        moduleStationKey: 'analisis-compra',
        puedeEditar: false,
        _guardando: false,
        _reintento: 0,
        updateStates: {},
        updateStateTimers: {},

        init() {
            const c = document.getElementById('container-analisis-compra');
            if (!c) return;
            this.idUsuario = parseInt(c.dataset.idUsuario || '0', 10) || 0;
            this.idEstacion = parseInt(c.dataset.idEstacion || '0', 10) || 0;
            this.idYear = parseInt(c.dataset.idYear || '0', 10) || 0;
            this.idMes = parseInt(c.dataset.idMes || '0', 10) || 0;
            this.moduleStationKey = c.dataset.moduleStationKey || 'analisis-compra';
            this.puedeEditar = c.dataset.puedeEditar === 'true';
        },

        getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },

        fmtValor(v) {
            const n = Math.round((Number(v) || 0) * 100) / 100;
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(n);
        },

        valorNumerico(valor) {
            const limpio = String(valor ?? '').replace(/,/g, '').trim();
            return limpio === '' ? NaN : Number(limpio);
        },

        formatearNumero(valor) {
            const limpio = String(valor ?? '').replace(/[^\d]/g, '');
            return limpio === '' ? '' : Number(limpio).toLocaleString('en-US');
        },

        actualizarVistaLocal(event, id, opcion) {
            const input = event && event.target ? event.target : null;
            if (!input) return;
            input.value = this.formatearNumero(input.value);
            input.classList.remove('is-invalid');
            input.style.border = '';
            if (id && opcion) {
                const key = id + '-' + opcion;
                clearTimeout(this.updateStateTimers[key]);
                delete this.updateStates[key];
            }
            this._recalcularDesdeInput(input);
        },

        _recalcularDesdeInput(input) {
            const card = input.closest('[data-producto]');
            if (!card || !card.dataset || !card.dataset.producto) return;
            this.recalcularPorProducto(card.dataset.producto);
        },

        recalcularPorProducto(nombreProducto) {
            if (!nombreProducto) return;

            const contenedor = this.$el || document.body;
            let cardDetalle = null;
            let cardSubtotal = null;

            if (contenedor.querySelectorAll) {
                contenedor.querySelectorAll('[data-producto="' + nombreProducto + '"]').forEach(n => {
                    if (n.classList.contains('col-12') && !cardDetalle) cardDetalle = n;
                    if ((n.classList.contains('col-xl-4') || n.classList.contains('col-lg-4')) && !cardSubtotal) cardSubtotal = n;
                });
            }

            if (!cardDetalle) return;

            const filas = [];
            cardDetalle.querySelectorAll('input[data-embarque-id][data-opcion]').forEach(inp => {
                const lts = parseFloat(inp.getAttribute('data-lts-factura')) || 0;
                filas.push({
                    id: inp.getAttribute('data-embarque-id'),
                    opcion: inp.getAttribute('data-opcion') === '2' ? 2 : 1,
                    lts: lts,
                    valor: this.valorNumerico(inp.value) || 0
                });
            });

            filas.forEach(f => {
                const campoSufijo = f.opcion === 1 ? 'dif1' : 'dif2';
                const dif = f.valor - f.lts;
                const celda = cardDetalle.querySelector('td[data-campo="' + campoSufijo + '"][data-embarque-id="' + f.id + '"]');
                if (celda) celda.textContent = this.fmtValor(dif);
            });

            const totales = { bruto: 0, neto: 0, dif1: 0, dif2: 0 };
            filas.forEach(f => {
                if (f.opcion === 1) {
                    totales.bruto += f.valor;
                    totales.dif1 += (f.valor - f.lts);
                } else {
                    totales.neto += f.valor;
                    totales.dif2 += (f.valor - f.lts);
                }
            });

            [['Bruto', 'bruto'], ['Neto', 'neto'], ['Dif1', 'dif1'], ['Dif2', 'dif2']].forEach(pair => {
                const celdaTotal = this._celdaTotalProducto(cardDetalle, pair[0]);
                if (celdaTotal) celdaTotal.textContent = this.fmtValor(totales[pair[1]]);
            });

            if (cardSubtotal) {
                const ltsPorId = {};
                filas.forEach(f => { if (!(f.id in ltsPorId)) ltsPorId[f.id] = f.lts; });
                const factura = Object.keys(ltsPorId).reduce((s, k) => s + ltsPorId[k], 0);
                const bruto = totales.bruto;
                const neto = totales.neto;
                const mB = factura - bruto;
                const mN = factura - neto;
                const pB = factura !== 0 ? mB / factura : 0;
                const pN = factura !== 0 ? mN / factura : 0;

                this._setFilaSubtotal(cardSubtotal, 'Factura', [this.fmtValor(factura)]);
                this._setFilaSubtotal(cardSubtotal, 'Bruto', [this.fmtValor(bruto), this.fmtValor(mB), this.fmtValor(pB) + '%']);
                this._setFilaSubtotal(cardSubtotal, 'Neto', [this.fmtValor(neto), this.fmtValor(mN), this.fmtValor(pN) + '%']);
            }
        },

        _celdaTotalProducto(card, label) {
            const fila = this._filaConEncabezado(card, label);
            if (!fila) return null;
            const celdas = fila.cells;
            return celdas[celdas.length - 1] || null;
        },

        _setFilaSubtotal(card, label, valores) {
            const fila = this._filaConEncabezado(card, label);
            if (!fila) return;
            const celdas = fila.cells;
            valores.forEach((v, i) => {
                const celda = celdas[i + 1];
                if (celda) celda.textContent = v;
            });
        },

        _filaConEncabezado(card, label) {
            const filas = card.querySelectorAll('table tbody tr');
            for (const tr of filas) {
                const th = tr.querySelector('th');
                if (th && th.textContent.trim() === label) return tr;
            }
            return null;
        },

        async actualizarBrutoNeto(event, id, opcion) {
            if (!this.puedeEditar || !id || ![1, 2].includes(opcion)) return;

            const input = event && event.target ? event.target : null;
            if (!input) return;

            const valorVisible = (input.value || '').trim();
            const valor = this.valorNumerico(valorVisible);
            const original = input.getAttribute('value') || '';
            const key = id + '-' + opcion;
            clearTimeout(this.updateStateTimers[key]);

            if (valorVisible === '') {
                input.classList.add('is-invalid');
                input.style.border = '2px solid #A52525';
                this.updateStates[key] = 'error';
                if (window.Notify) Notify.error('Completa los campos obligatorios.');
                return;
            }

            if (!Number.isFinite(valor) || valor < 0) {
                input.classList.add('is-invalid');
                input.style.border = '2px solid #A52525';
                this.updateStates[key] = 'error';
                if (window.Notify) Notify.error('Completa los campos obligatorios.');
                this._recalcularDesdeInput(input);
                input.focus();
                return;
            }

            this.updateStates[key] = 'saving';

            try {
                const res = await axios.post(
                    '/departamento-operativo/importacion/analisis-compra/update',
                    { id: id, opcion: opcion, valor: valor },
                    { headers: {
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                        'X-Portal3-Skip-Action-Loader': '1'
                    } }
                );

                if (res.data && res.data.success) {
                    const guardado = (res.data.valor !== undefined && res.data.valor !== null) ? String(res.data.valor) : String(valor);

                    if (this._reintento < 1 && this.valorNumerico(input.value) !== Number(guardado)) {
                        this._reintento++;
                        input.setAttribute('value', this.formatearNumero(guardado));
                        input.value = this.formatearNumero(guardado);
                        await this.actualizarBrutoNeto({ target: input }, id, opcion);
                        return;
                    }
                    this._reintento = 0;

                    this._reemplazarSeccion(res.data, id, opcion, input, this.formatearNumero(guardado));
                    const inputActualizado = this.$el.querySelector('input[data-embarque-id="' + id + '"][data-opcion="' + opcion + '"]');
                    if (inputActualizado) {
                        inputActualizado.classList.remove('is-invalid');
                        inputActualizado.style.border = '';
                    }
                    this.updateStates[key] = 'saved';
                    this.updateStateTimers[key] = setTimeout(() => { delete this.updateStates[key]; }, 1800);
                } else {
                    input.classList.add('is-invalid');
                    input.style.border = '2px solid #A52525';
                    this.updateStates[key] = 'error';
                    this._recalcularDesdeInput(input);
                    if (window.Notify) Notify.error((res.data && res.data.message) || 'No fue posible actualizar el registro.');
                }
            } catch (err) {
                input.classList.add('is-invalid');
                input.style.border = '2px solid #A52525';
                this.updateStates[key] = 'error';
                this._recalcularDesdeInput(input);
                const msg = (err.response && err.response.data && err.response.data.message) || 'Error de conexión al actualizar.';
                if (window.Notify) Notify.error(msg);
            } finally {
                input.setAttribute('value', input.value || original);
                if (this.updateStates[key] === 'error') {
                    clearTimeout(this.updateStateTimers[key]);
                    this.updateStateTimers[key] = setTimeout(() => { delete this.updateStates[key]; }, 3000);
                }
            }
        },

        _reemplazarSeccion(data, id, opcion, inputOriginal, guardado) {
            const selector = '[data-producto="' + (data.producto || '') + '"]';
            let nodoDetalle = null;
            let nodoSubtotal = null;

            if (this.$el && data.producto) {
                this.$el.querySelectorAll(selector).forEach(n => {
                    if (n.classList.contains('col-12') && !nodoDetalle) {
                        nodoDetalle = n;
                    } else if ((n.classList.contains('col-xl-4') || n.classList.contains('col-lg-4')) && !nodoSubtotal) {
                        nodoSubtotal = n;
                    }
                });
            }

            if (nodoDetalle && data.detalle) {
                const tmp = document.createElement('div');
                tmp.innerHTML = data.detalle.trim();
                const nuevoDetalle = tmp.querySelector(selector);
                if (nuevoDetalle) {
                    nodoDetalle.replaceWith(nuevoDetalle);
                    if (window.Alpine && typeof Alpine.initTree === 'function') {
                        Alpine.initTree(nuevoDetalle);
                    }
                }
            }

            if (nodoSubtotal && data.subtotal) {
                const tmp = document.createElement('div');
                tmp.innerHTML = data.subtotal.trim();
                const nuevoSubtotal = tmp.querySelector(selector);
                if (nuevoSubtotal) {
                    nodoSubtotal.replaceWith(nuevoSubtotal);
                }
            }

            const nuevoInput = this.$el
                ? this.$el.querySelector('input[data-embarque-id="' + id + '"][data-opcion="' + opcion + '"]')
                : null;

            if (nuevoInput) {
                nuevoInput.setAttribute('value', guardado);
                nuevoInput.dataset.originalValue = guardado;
                nuevoInput.value = guardado;
                nuevoInput.focus();
            } else if (inputOriginal) {
                inputOriginal.setAttribute('value', guardado);
                inputOriginal.value = guardado;
            }

            if (data && data.producto) {
                this.recalcularPorProducto(data.producto);
            }
        }
    }));
});
