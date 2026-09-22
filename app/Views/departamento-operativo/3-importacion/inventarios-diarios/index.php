<div id="container" class="mt-4 mb-5"
     data-id-year="<?= (int)$idYear ?>"
     data-id-mes="<?= (int)$idMes ?>"
     data-year-mes-template="<?= htmlspecialchars($yearMesTemplate, ENT_QUOTES, 'UTF-8') ?>"
     data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
     data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
     data-reportes='<?= htmlspecialchars(json_encode($reportes, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     x-data="{ ...actions(), ...inventariosDiariosInitComponent() }">

    <div class="row">
        <div class="col-12">
            <div class="d-flex float-end justify-content-between flex-wrap gap-2 mb-3">
                <button type="button" class="btn bg-primary-subtle text-primary" x-show="puedeCrear" x-on:click="nuevoReporte()" :disabled="loading">
                    <i class="ti ti-plus me-1"></i> Nuevo
                </button>
            </div>
        </div>
    </div>

    <template x-if="cargado && reportes.length === 0">
        <div class="alert alert-secondary border-0 text-center text-muted py-4" x-text="tituloVacio"></div>
    </template>

    <template x-if="cargado && reportes.length > 0">
        <div class="row">
            <template x-for="r in reportes" :key="r.id">
                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                    <div class="card">
                        <div class="card-header fw-semibold text-white d-flex align-items-center" :class="r.estatus === 0 ? 'text-bg-warning' : 'text-bg-primary'">
                          
                        <h4  class="mb-0 text-white card-title">
                        <i class="ti ti-calendar me-1"></i> <span x-text="r.fecha_label"></span>
                        </h4>
                    
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0 text-nowrap align-middle">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle">Sucursal</th>
                                            <th class="text-center align-middle">Destino</th>
                                            <th class="text-center align-middle text-white" :style="'background: ' + colores.oct87">87 Oct</th>
                                            <th class="text-center align-middle text-white" :style="'background: ' + colores.oct91">91 Oct</th>
                                            <th class="text-center align-middle text-white" :style="'background: ' + colores.diesel">Diesel</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="s in r.sucursales" :key="s.id">
                                            <tr>
                                                <td class="text-center align-middle" x-text="s.sucursal"></td>
                                                <td class="text-center align-middle" x-text="celdaTexto(s.destino)"></td>
                                                <td class="text-center align-middle" x-text="celdaTexto(s.oct87)" :style="colorCelda(s.oct87, colores.oct87)"></td>
                                                <td class="text-center align-middle" x-text="celdaTexto(s.oct91)" :style="colorCelda(s.oct91, colores.oct91)"></td>
                                                <td class="text-center align-middle" x-text="celdaTexto(s.diesel)" :style="colorCelda(s.diesel, colores.diesel)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
              
                        </div>

              <div class="modal-footer">
                                <a x-show="puedeEditar" :href="'/departamento-operativo/importacion/inventarios-diarios-reporte/' + r.id" class="btn bg-warning-subtle text-warning w-50 rounded-0">
                                    <i class="ti ti-pencil me-1"></i> Editar
                                </a>
                                <button type="button" x-show="puedeEliminar" class="btn bg-danger-subtle text-danger w-50 rounded-0" x-on:click="eliminarReporte(r.id, r.fecha_label)">
                                    <i class="ti ti-trash me-1"></i> Eliminar
                                </button>
                            </div>


                    </div>
                </div>
            </template>
        </div>
    </template>

</div>