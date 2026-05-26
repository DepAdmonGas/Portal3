document.addEventListener('alpine:init', () => {

Alpine.data('impuestosComponent', () => ({
    idDia: null,
    loading: true,

    items: [],
    aceitesTotal: 0,
    aceitesSinIva: 0,
    aceitesIva: 0,
    subtotales: { volumen: 0, importe_sin_iva: 0, iva: 0, ieps: 0, total: 0 },
    totales: { importe_sin_iva: 0, iva: 0, ieps: 0, total: 0 },

    init() {
        const c = document.getElementById('container');
        if (!c) return;
        this.idDia = parseInt(c.dataset.idDia);
        this.cargarDatos();
    },

    async cargarDatos() {
        this.loading = true;
        try {
            const resp = await fetch('/departamento-operativo/impuestos/data/' + this.idDia);
            const json = await resp.json();
            if (json.success) {
                const d = json.data;
                this.items = d.items || [];
                this.aceitesTotal = d.aceites_total || 0;
                this.aceitesSinIva = d.aceites_sin_iva || 0;
                this.aceitesIva = d.aceites_iva || 0;
                this.subtotales = d.subtotal_combustibles || { volumen: 0, importe_sin_iva: 0, iva: 0, ieps: 0, total: 0 };
                this.totales = d.total_dia || { importe_sin_iva: 0, iva: 0, ieps: 0, total: 0 };
            }
        } catch (e) {
            console.error('Error cargando impuestos:', e);
        } finally {
            this.loading = false;
        }
    },

    formatNum(v) {
        return parseFloat(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
}));

});
