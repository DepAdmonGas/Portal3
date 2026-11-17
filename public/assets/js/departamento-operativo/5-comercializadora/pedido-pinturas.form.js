// Validación y captura compartidas únicamente por Pedido de Pinturas.
window.PedidoPinturasForm = function () {
    return {
        validationErrors: {},
        getFirma(tipo) { return this.pedidoActual?.firmas?.[tipo] ?? null; },
        validateFields(fields, notify = true) {
            this.validationErrors = fields;
            const valid = Object.values(fields).every(Boolean);
            if (!valid && notify && window.Notify) Notify.error('Completa los campos obligatorios.');
            return valid;
        },
        clearField(field) { this.validationErrors[field] = true; },
        invalid(field) { return this.validationErrors[field] === false; },
        positiveQuantity(value) {
            const normalized = String(value ?? '').replace(/,/g, '');
            return /^\d+$/.test(normalized) && Number.isSafeInteger(Number(normalized))
                && Number(normalized) > 0 && Number(normalized) <= 2147483647;
        },
        // Mismo agrupamiento y conservación del cursor que Ventas; piezas es entero.
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
            const digitsBeforeCursor = raw.slice(0, cursor).replace(/[^0-9]/g, '').length;
            const normalized = raw.replace(/[^0-9]/g, '');
            const formatted = normalized.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            row[field] = normalized;
            input.value = formatted;
            let nextCursor = 0, numericChars = 0;
            while (nextCursor < formatted.length && numericChars < digitsBeforeCursor) {
                if (/[0-9]/.test(formatted[nextCursor])) numericChars++;
                nextCursor++;
            }
            input.setSelectionRange(nextCursor, nextCursor);
            if (this.positiveQuantity(normalized)) this.clearField(field);
        },
        displayQuantity(value) {
            return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
};
