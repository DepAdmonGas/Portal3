window.PedidoLimpiezaForm = function () {
    return {
        validationErrors: {},
        signaturePad: null,
        firmaVacia: true,
        resizeFirma: null,
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
        formatInputNumber(value) {
            if (value === null || value === undefined || value === '') return '';
            return String(value).replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        formatNumericInput(event, row, field) {
            const input = event.target;
            const raw = input.value;
            const cursor = input.selectionStart ?? raw.length;
            const digitsBeforeCursor = raw.slice(0, cursor).replace(/\D/g, '').length;
            const normalized = raw.replace(/\D/g, '');
            const formatted = normalized.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            row[field] = normalized;
            input.value = formatted;
            let nextCursor = 0, numericChars = 0;
            while (nextCursor < formatted.length && numericChars < digitsBeforeCursor) {
                if (/\d/.test(formatted[nextCursor])) numericChars++;
                nextCursor++;
            }
            input.setSelectionRange(nextCursor, nextCursor);
            if (this.positiveQuantity(normalized)) this.clearField(field);
        },
        displayQuantity(value) {
            return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
        }
    };
};
