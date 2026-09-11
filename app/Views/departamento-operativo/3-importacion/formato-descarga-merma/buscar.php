<div id="container" class="mt-4 mb-5"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
     data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
     data-id-estacion="<?= $idEstacion ?? 0 ?>"
     x-data="{ ...actions(), ...mermaBuscar() }">

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="/departamento-operativo/importacion/formato-descarga-merma" class="btn bg-secondary-subtle text-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="ti ti-search me-2"></i><?= htmlspecialchars($title) ?></h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Año:</label>
                    <select class="form-select" x-model="busqueda.year">
                        <option value="">Selecciona año...</option>
                        <?php for ($y = date('Y'); $y >= 2022; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Mes:</label>
                    <select class="form-select" x-model="busqueda.mes">
                        <option value="">Selecciona mes...</option>
                        <option value="1">Enero</option>
                        <option value="2">Febrero</option>
                        <option value="3">Marzo</option>
                        <option value="4">Abril</option>
                        <option value="5">Mayo</option>
                        <option value="6">Junio</option>
                        <option value="7">Julio</option>
                        <option value="8">Agosto</option>
                        <option value="9">Septiembre</option>
                        <option value="10">Octubre</option>
                        <option value="11">Noviembre</option>
                        <option value="12">Diciembre</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-primary" @click="buscar()" :disabled="buscando || !busqueda.year || !busqueda.mes">
                        <template x-if="!buscando"><i class="ti ti-search me-1"></i></template>
                        <template x-if="buscando"><span class="spinner-border spinner-border-sm me-1"></span></template>
                        Buscar
                    </button>
                </div>
            </div>

            <?php if ($puedeDescargar): ?>
            <div class="d-flex gap-2 mb-3" x-show="resultados.length > 0">
                <button type="button" class="btn btn-outline-success btn-sm" @click="descargarExcel()">
                    <i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel (estación seleccionada)
                </button>
                <button type="button" class="btn btn-outline-success btn-sm" @click="descargarExcelGeneral()">
                    <i class="ti ti-table me-1"></i> Descargar Excel General (todas las estaciones)
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm" x-show="resultados.length > 0 || mensaje">
        <div class="card-body">
            <template x-if="mensaje">
                <div class="alert alert-info text-center" x-text="mensaje"></div>
            </template>
            <div class="table-responsive" x-show="resultados.length > 0">
                <table class="table table-striped table-bordered text-nowrap align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>Folio</th>
                            <th>Fecha llegada</th>
                            <th>Producto</th>
                            <th>No. Factura/Remisión</th>
                            <th>Litros</th>
                            <th>Precio Litro</th>
                            <th>Cuenta Litros</th>
                            <th>Tolerancia</th>
                            <th>Merma</th>
                            <th>N.C</th>
                            <th>Importe N.C</th>
                            <th>Unidad</th>
                            <th>Operador</th>
                            <th>Transportista</th>
                            <th>Sellos</th>
                            <th>Detuvo Venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="r in resultados" :key="r.id">
                            <tr>
                                <td x-text="r.folio"></td>
                                <td x-text="r.fecha_llegada"></td>
                                <td x-text="r.producto"></td>
                                <td x-text="r.no_factura_remision"></td>
                                <td x-text="r.litros"></td>
                                <td x-text="'$' + r.precio_litro"></td>
                                <td x-text="r.cuenta_litros"></td>
                                <td x-text="r.tolerancia"></td>
                                <td x-text="r.merma"></td>
                                <td x-text="r.nc"></td>
                                <td x-text="'$' + r.importe_nc"></td>
                                <td x-text="r.unidad"></td>
                                <td x-text="r.operador"></td>
                                <td x-text="r.transportista"></td>
                                <td x-text="r.sellos"></td>
                                <td x-text="r.detuvo_venta"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('mermaBuscar', () => ({
        busqueda: { year: '', mes: '' },
        resultados: [],
        mensaje: '',
        buscando: false,

        async buscar() {
            if (!this.busqueda.year || !this.busqueda.mes) {
                this.notify('error', 'Selecciona año y mes.');
                return;
            }

            this.buscando = true;
            this.mensaje = '';
            this.resultados = [];

            try {
                const container = document.getElementById('container');
                const estacion = parseInt(container?.dataset.idEstacion || '0');

                const res = await axios.get('/departamento-operativo/importacion/formato-descarga-merma/buscar-data', {
                    params: { estacion: estacion, year: this.busqueda.year, mes: this.busqueda.mes }
                });

                if (res.data.success) {
                    this.resultados = res.data.data;
                    if (this.resultados.length === 0) {
                        this.mensaje = 'No se encontró información para los criterios seleccionados.';
                    }
                } else {
                    this.notify('error', res.data.message);
                }
            } catch (err) {
                const msg = err.response?.data?.message || err.message || 'Error al buscar.';
                this.notify('error', msg);
            } finally {
                this.buscando = false;
            }
        },

        descargarExcel() {
            const container = document.getElementById('container');
            const estacion = parseInt(container?.dataset.idEstacion || '0');
            const params = new URLSearchParams({
                estacion: estacion,
                year: this.busqueda.year,
                mes: this.busqueda.mes
            });
            window.location.href = '/departamento-operativo/importacion/formato-descarga-merma/excel-busqueda?' + params.toString();
        },

        descargarExcelGeneral() {
            const params = new URLSearchParams({
                year: this.busqueda.year,
                mes: this.busqueda.mes
            });
            window.location.href = '/departamento-operativo/importacion/formato-descarga-merma/excel-general?' + params.toString();
        }
    }));
});
</script>
