<div id="container" class="mt-4 mb-5"
     data-id-reporte="<?= (int)$reporte['id'] ?>"
     data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
     data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
     data-reporte='<?= htmlspecialchars(json_encode($reporte, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     x-data="{ ...actions(), ...inventariosReporteComponent() }">

<div class="row align-items-end mb-3">
    <!-- Lado izquierdo: Fecha e Input (Ocupa la mitad en pantallas medianas/grandes y todo el ancho en celulares) -->
    <div class="col-xl-4 col-lg-5 col-md-6 col-12 mb-2 mb-md-0">
        <label class="mb-1 form-label">* Fecha:</label>
        <input type="date" class="form-control" id="Fecha" x-model="fecha">
    </div>

    <!-- Lado derecho: Botones (Alineados a la derecha en pantallas medianas/grandes, y a la izquierda o completo en celulares según prefieras) -->
    <div class="col-xl-8 col-lg-7 col-md-6 col-12">
        <div class="d-flex justify-content-md-end justify-content-start flex-wrap gap-2">
            <button type="button" class="btn bg-success text-white" x-show="puedeEditar" x-on:click="finalizar()" :disabled="guardando">
                <i class="ti ti-check me-1"></i> Finalizar inventario
            </button>
                        <button type="button" class="btn bg-primary-subtle text-primary" x-show="puedeCrear" x-on:click="abrirModal()">
                <i class="ti ti-plus me-1"></i> Nuevo
            </button>
        </div>
    </div>
</div>


    <div class="col-12">

<div class="card">

                        <div class="card-header fw-semibold text-white d-flex align-items-center bg-primary">
                          
                        <h4  class="mb-0 text-white card-title">
                        <i class="ti ti-gas-station me-1"></i> INVENTARIOS REALES</span>
                        </h4>
                    
                        </div>

<div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <thead>
                    <tr>
                        <th class="text-start align-middle">Sucursal</th>
                        <th class="text-center align-middle" width="200px">Destino</th>
                        <th class="text-center align-middle" width="200px">87 Oct</th>
                        <th class="text-center align-middle" width="200px">91 Oct</th>
                        <th class="text-center align-middle" width="200px">Diesel</th>
                        <th class="text-center align-middle" width="48px" x-show="puedeEditar">
                            <i class="ti ti-trash text-danger fs-6"></i>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="sucursales.length === 0">
                        <tr>
                            <td :colspan="puedeEditar ? 6 : 5" class="text-center text-secondary"><small>No se encontró información para mostrar</small></td>
                        </tr>
                    </template>
                    <template x-for="s in sucursales" :key="s.id">
                        <tr>
                            <td class="text-start align-middle fw-semibold"><span x-text="s.sucursal"></span></td>
                            <td class="align-middle text-center" x-text="celdaTexto(s.destino)"></td>
                            <td class="p-0 align-middle text-center">
                           <input type="number" min="0" class="border-0 p-3 w-100 bg-transparent text-center"
       :value="celdaTexto(s.oct87)"
       :disabled="!puedeEditar"
       x-on:input.debounce="editarDestino($event, s.id, 2)">
                            </td>
                            <td class="p-0 align-middle text-center">
                       <input type="number" min="0" class="border-0 p-3 w-100 bg-transparent text-center"
       :value="celdaTexto(s.oct91)"
       :disabled="!puedeEditar"
       x-on:input.debounce="editarDestino($event, s.id, 3)">
                            </td>
                            <td class="p-0 align-middle text-center">
                           <input type="number" min="0" class="border-0 p-3 w-100 bg-transparent text-center"
       :value="celdaTexto(s.diesel)"
       :disabled="!puedeEditar"
       x-on:input.debounce="editarDestino($event, s.id, 4)">
                            </td>
                            <td class="align-middle text-center" x-show="puedeEditar">
                                <i class="ti ti-trash text-danger fs-6 pointer" x-on:click="eliminarDetalle(s.id)"></i>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
</div>

</div>



    </div>

    <!-- Modal Nuevo registro -->
    <div class="modal fade" id="modalAgregarSucursal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">



                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"> <i class="ti ti-gas-station"></i> Nuevo registro</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>


                <div class="modal-body">
                    <label class="form-label">* Sucursal:</label>
                    <input type="text" class="form-control mb-3" id="inputSucursal" x-model="form.Sucursal">


                    <h5 class="text-primary">INVENTARIOS REALES</h5>
                             
                                <div class="row">
                                    <div class="col-xl-6 col-lg-3 col-md-12 col-sm-12">
                                        <label class="form-label">* Destino:</label>
                                        <input type="number" class="form-control mb-3" x-model="form.Destino1">
                                    </div>
                                    <div class="col-xl-6 col-lg-3 col-md-12 col-sm-12">
                                        <label class="form-label">87 OCT:</label>
                                        <input type="number" class="form-control mb-3" x-model="form.Oct871">
                                    </div>
                                    <div class="col-xl-6 col-lg-3 col-md-12 col-sm-12">
                                        <label class="form-label">91 OCT:</label>
                                        <input type="number" class="form-control mb-3" x-model="form.Oct911">
                                    </div>
                                    <div class="col-xl-6 col-lg-3 col-md-12 col-sm-12">
                                        <label class="form-label">Diesel:</label>
                                        <input type="number" class="form-control" x-model="form.Diesel1">
                                    </div>
                                </div>

                                <h5 class="text-primary">CAPACIDAD ALMACENAJE</h5>

                                                           <div class="row">
                                    <div class="col-xl-6 col-lg-3 col-md-12 col-sm-12">
                                        <label class="form-label">* Destino:</label>
                                        <input type="number" class="form-control mb-3" x-model="form.Destino2">
                                    </div>
                                    <div class="col-xl-6 col-lg-3 col-md-12 col-sm-12">
                                        <label class="form-label">87 OCT:</label>
                                        <input type="number" class="form-control mb-3" x-model="form.Oct872">
                                    </div>
                                    <div class="col-xl-6 col-lg-3 col-md-12 col-sm-12">
                                        <label class="form-label">91 OCT:</label>
                                        <input type="number" class="form-control mb-3" x-model="form.Oct912">
                                    </div>
                                    <div class="col-xl-6 col-lg-3 col-md-12 col-sm-12">
                                        <label class="form-label">Diesel:</label>
                                        <input type="number" class="form-control" x-model="form.Diesel2">
                                    </div>
                                </div>


                </div>
                
                <div class="modal-footer">

            <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                    <i class="ti ti-x"></i> Cancelar
                </button>

            <button type="button"
                        class="btn bg-success text-white"
                        x-on:click="guardarSucursal()" :disabled="guardando">
                   <i class="ti ti-check me-1"></i> Guardar
                </button>
                
                </div>
            </div>
        </div>
    </div>

</div>