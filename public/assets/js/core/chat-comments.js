(function () {
    'use strict';

    const dateKeys = ['created_at', 'fecha_hora', 'fecha_creacion', 'fecha_registro', 'fecha', 'createdAt', 'timestamp'];
    const idKeys = ['id', 'id_comentario', 'comentario_id'];

    function firstValue(item, keys) {
        for (const key of keys) {
            if (item && item[key] !== undefined && item[key] !== null && item[key] !== '') {
                return item[key];
            }
        }
        return null;
    }

    function timestamp(item) {
        const value = firstValue(item, dateKeys);
        if (value === null) return null;
        const parsed = Date.parse(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed) ? null : parsed;
    }

    window.ordenarComentariosChat = function (items) {
        if (!Array.isArray(items)) return [];

        return items
            .map((item, index) => {
                const id = firstValue(item, idKeys);
                return { item, index, time: timestamp(item), id: id === null ? Number.NaN : Number(id) };
            })
            .sort((a, b) => {
                if (a.time !== null && b.time !== null && a.time !== b.time) return a.time - b.time;

                const aHasId = Number.isFinite(a.id);
                const bHasId = Number.isFinite(b.id);
                if (aHasId && bHasId && a.id !== b.id) return a.id - b.id;

                if (a.time !== null && b.time === null) return -1;
                if (a.time === null && b.time !== null) return 1;
                return a.index - b.index;
            })
            .map(({ item }) => item);
    };

    const etiquetasContexto = {
        estacion: 'Estación', nombre_estacion: 'Estación', departamento: 'Departamento', nombre_departamento: 'Departamento',
        solicitante: 'Solicitante', nombre_solicitante: 'Solicitante', usuario: 'Usuario', beneficiario: 'Beneficiario',
        fecha: 'Fecha', fecha_solicitud: 'Fecha', fecha_creacion: 'Fecha', fecha_registro: 'Fecha', fecha_hora: 'Fecha', created_at: 'Fecha', periodo: 'Periodo', etiqueta: 'Fecha',
        concepto: 'Concepto', concepto_solicitud: 'Concepto', asunto: 'Asunto', tema: 'Tema', actividad: 'Actividad', tipo: 'Tipo', producto: 'Producto', documento: 'Documento',
        descripcion: 'Descripción', description: 'Descripción', detalle: 'Detalle', observaciones: 'Observaciones', motivo: 'Motivo', justificacion: 'Justificación',
        estatus: 'Estatus', estado_texto: 'Estatus', status: 'Estatus', status_label: 'Estatus', prioridad: 'Prioridad', porcentaje: 'Avance',
        monto: 'Monto', importe: 'Importe', total_pago: 'Total', total_solicitud: 'Total', total: 'Total', costo: 'Costo', precio: 'Precio',
        cantidad: 'Cantidad', volumen: 'Volumen', razon_social: 'Razón social', proveedor: 'Proveedor', destino: 'Destino',
        numero: 'Número', folio: 'Folio'
    };

    function normalizarClave(clave) {
        return String(clave).replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase();
    }

    function registroTablaPorId(id) {
        const $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.dataTable || id === null || id === undefined || id === '') return null;

        try {
            const api = $.fn.dataTable.tables({ api: true });
            let encontrado = null;
            api.every(function () {
                if (encontrado) return;
                this.rows().every(function () {
                    if (encontrado) return;
                    const fila = this.data();
                    if (!fila || typeof fila !== 'object' || Array.isArray(fila)) return;
                    const entradas = Object.entries(fila);
                    const coincidePrimario = entradas.some(([clave, valor]) => {
                        const claveNormalizada = normalizarClave(clave);
                        return /^(id|folio|no_folio|numero_folio|folio_[a-z0-9_]+)$/i.test(claveNormalizada)
                            && String(valor ?? '') === String(id);
                    });
                    const coincideAlias = !coincidePrimario && entradas.some(([clave, valor]) => {
                        const claveNormalizada = normalizarClave(clave);
                        return /^id_(?!estacion$|departamento$|usuario$|puesto$|tipo$|year$|mes$|fecha$|producto$|proveedor$|comentario$|responsable$|personal_soporte$)[a-z0-9_]+$/i.test(claveNormalizada)
                            && String(valor ?? '') === String(id);
                    });
                    if (coincidePrimario || coincideAlias) encontrado = fila;
                });
            });

            if (encontrado) {
                return { ...(registroVisiblePorId(id) || {}), ...encontrado };
            }
        } catch (error) {
            console.debug('No se pudo resolver el resumen desde DataTables:', error);
        }

        return registroVisiblePorId(id);
    }

    function claveDesdeEncabezado(texto) {
        const encabezado = normalizarClave(texto.normalize('NFD').replace(/[\u0300-\u036f]/g, ''));
        if (/estacion/.test(encabezado)) return 'estacion';
        if (/departamento/.test(encabezado)) return 'departamento';
        if (/fecha|periodo|dia/.test(encabezado)) return 'fecha';
        if (/concepto|asunto|tema|actividad/.test(encabezado)) return 'concepto';
        if (/descripcion|detalle|observacion|motivo/.test(encabezado)) return 'descripcion';
        if (/monto|importe|total|costo|precio/.test(encabezado)) return 'monto';
        if (/beneficiario/.test(encabezado)) return 'beneficiario';
        if (/solicitante|usuario|empleado|personal/.test(encabezado)) return 'solicitante';
        if (/estatus|estado|status/.test(encabezado)) return 'estatus';
        if (/folio|numero|^#$/.test(encabezado)) return 'folio';
        if (/proveedor/.test(encabezado)) return 'proveedor';
        if (/producto/.test(encabezado)) return 'producto';
        if (/cantidad|volumen/.test(encabezado)) return 'cantidad';
        if (/prioridad/.test(encabezado)) return 'prioridad';
        return encabezado.replace(/[^a-z0-9_]+/g, '_').replace(/^_|_$/g, '');
    }

    function registroVisiblePorId(id) {
        if (typeof document === 'undefined' || id === null || id === undefined || id === '') return null;
        const botones = Array.from(document.querySelectorAll('[data-id]'))
            .filter((elemento) => elemento.getAttribute('data-id') === String(id));

        for (const boton of botones) {
            const fila = boton.closest('tr');
            if (!fila) continue;
            const tabla = fila.closest('table');
            const encabezados = tabla ? Array.from(tabla.querySelectorAll('thead th')) : [];
            const celdas = Array.from(fila.children).filter((celda) => /^(TD|TH)$/.test(celda.tagName));
            const registro = { id };

            celdas.forEach((celda, indice) => {
                const encabezado = encabezados[indice];
                if (!encabezado) return;
                const clave = claveDesdeEncabezado(encabezado.innerText || encabezado.textContent || '');
                const valor = (celda.innerText || celda.textContent || '').trim();
                if (clave && valor && !registro[clave]) registro[clave] = valor;
            });

            if (Object.keys(registro).length > 1) return registro;
        }

        return null;
    }

    function valorVisible(valor) {
        if (valor === null || valor === undefined || typeof valor === 'object') return '';
        const texto = String(valor).trim();
        return texto === '' || texto === '-' || texto === '—' ? '' : texto;
    }

    window.resumenContextoComentario = function (contexto) {
        const idDeContexto = contexto && typeof contexto === 'object'
            ? Object.entries(contexto).find(([clave, valor]) => {
                const claveNormalizada = normalizarClave(clave);
                return (/^(id|id_(?!estacion$|departamento$|usuario$|puesto$|tipo$|year$|mes$|fecha$|producto$|proveedor$|comentario$|responsable$|personal_soporte$)[a-z0-9_]+)$/i.test(claveNormalizada)
                    || /^(folio|no_folio|numero_folio)$/i.test(claveNormalizada))
                    && valorVisible(valor) !== '';
            })?.[1]
            : contexto;
        const registroTabla = registroTablaPorId(idDeContexto);
        const registroOriginal = contexto && typeof contexto === 'object'
            ? { ...(registroTabla || {}), ...contexto }
            : registroTabla;
        const registro = registroOriginal || (contexto !== null && contexto !== undefined && contexto !== ''
            ? { id: contexto }
            : null);
        if (!registro) return null;

        const entradas = Object.entries(registro).map(([clave, valor]) => [clave, valor, normalizarClave(clave)]);
        const obtener = (regex) => {
            const entrada = entradas.find(([, valor, claveNormalizada]) => regex.test(claveNormalizada) && valorVisible(valor) !== '');
            return entrada ? entrada[1] : '';
        };

        const id = obtener(/^(id|id_(?!estacion$|departamento$|usuario$|puesto$|tipo$|year$|mes$|fecha$|producto$|proveedor$|comentario$|responsable$|personal_soporte$)[a-z0-9_]+)$/i)
            || (typeof contexto !== 'object' ? contexto : '');
        const folio = obtener(/^(folio|no_folio|numero_folio|folio_solicitud)$/i);
        const referenciaCandidata = valorVisible(folio) || valorVisible(id);
        const referencia = referenciaCandidata === '0' ? '' : referenciaCandidata;

        const descripcion = obtener(/^(descripcion|description|detalle|observaciones|motivo|justificacion|concepto_solicitud|.+_descripcion|.+_description|.+_concepto)$/i);
        const concepto = obtener(/^(concepto|concepto_solicitud|asunto|tema|actividad|producto|tipo_documento|.+_concepto)$/i);
        const prioritarias = [
            [/(estacion|departamento)/i, ''],
            [/(solicitante|usuario|personal|empleado|beneficiario)/i, ''],
            [/^(fecha|created_at|periodo|mes_nombre|etiqueta)/i, ''],
            [/^(estatus|estado_texto|status|status_label|prioridad|porcentaje|cantidad|volumen)/i, ''],
            [/^(monto|importe|total($|_(?!comentarios|registros|documentos))|costo|precio)/i, ''],
            [/^(razon_social|proveedor|destino|documento|tipo)$/i, '']
        ];
        const campos = [];
        const clavesAgregadas = new Set();

        if (concepto) {
            campos.push({ etiqueta: 'Concepto', valor: valorVisible(concepto) });
            const entradaConcepto = entradas.find(([, valor, claveNormalizada]) => /^(concepto|asunto|tema|actividad|producto|tipo_documento)$/i.test(claveNormalizada) && valorVisible(valor) === valorVisible(concepto));
            if (entradaConcepto) clavesAgregadas.add(entradaConcepto[0]);
        }

        for (const [patron] of prioritarias) {
            const entrada = entradas.find(([clave, valor, claveNormalizada]) => patron.test(claveNormalizada) && valorVisible(valor) !== '' && !clavesAgregadas.has(clave));
            if (!entrada) continue;
            const claveNormalizada = entrada[2];
            let etiqueta = etiquetasContexto[claveNormalizada];
            if (!etiqueta && /estacion|departamento/i.test(claveNormalizada)) etiqueta = /departamento/i.test(claveNormalizada) ? 'Departamento' : 'Estación';
            if (!etiqueta && /^(fecha|created_at|periodo|mes_nombre|etiqueta)/i.test(claveNormalizada)) etiqueta = 'Fecha / periodo';
            if (!etiqueta && /(solicitante|usuario|personal|empleado|beneficiario)/i.test(claveNormalizada)) etiqueta = 'Solicitante';
            if (!etiqueta && /^(monto|importe|total|costo|precio)/i.test(claveNormalizada)) etiqueta = 'Monto';
            etiqueta = etiqueta || claveNormalizada.replace(/_/g, ' ').replace(/^./, (letra) => letra.toUpperCase());
            const monto = /^(monto|importe|total|costo|precio)/i.test(claveNormalizada);
            let valor = valorVisible(entrada[1]);
            if (monto && Number.isFinite(Number(valor))) {
                valor = Number(valor).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
            }
            campos.push({ etiqueta, valor });
            clavesAgregadas.add(entrada[0]);
            if (campos.length >= 4) break;
        }

        const descripcionVisible = valorVisible(descripcion || concepto);
        if (!referencia && campos.length === 0 && !descripcionVisible) return null;

        return {
            referencia: valorVisible(referencia),
            campos,
            descripcion: descripcionVisible,
            concepto: valorVisible(concepto)
        };
    };

    function scrollToLatest(container) {
        if (!container) return;
        requestAnimationFrame(() => requestAnimationFrame(() => {
            container.scrollTop = container.scrollHeight;
        }));
    }

    function scrollChatsIn(element) {
        if (!element) return;
        if (element.matches && element.matches('.chat-box-inner, [x-ref="chatContainer"], [x-ref="chatCre"]')) {
            scrollToLatest(element);
        }
        element.querySelectorAll('.chat-box-inner, [x-ref="chatContainer"], [x-ref="chatCre"]')
            .forEach(scrollToLatest);
    }

    document.addEventListener('shown.bs.modal', event => {
        scrollChatsIn(event.target);
        window.setTimeout(() => scrollChatsIn(event.target), 80);
    });
    document.addEventListener('shown.bs.offcanvas', event => {
        scrollChatsIn(event.target);
        window.setTimeout(() => scrollChatsIn(event.target), 80);
    });
    document.addEventListener('shown.bs.collapse', event => {
        scrollChatsIn(event.target);
        window.setTimeout(() => scrollChatsIn(event.target), 80);
    });

})();
