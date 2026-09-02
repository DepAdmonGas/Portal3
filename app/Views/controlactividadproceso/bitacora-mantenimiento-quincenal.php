<div id="container" class="mb-4"
x-data="{ ...actions(), ...mantenimientoQuincenal()}"
data-carpeta="<?= htmlspecialchars($carpeta) ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">


<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

     <div class="text-end">
 
         <button type="button" class="btn bg-primary-subtle text-primary"><a class="dropdown-item pointer"  href="javascript:void(0)" @click="openNuevoModal()"><i class="ti ti-plus"></i> Nuevo </a></button>
    
</div>

<div class="datatables">
<div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
<table
    id="table-mantenimiento-quincenal"
    class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Folio</th>
            <th>Formato de Mantenimiento PREVENTIVO</th>
            <th>Pruebade sensores</th>
            <th>CUMPLIMIENTO A LOS APARTADOS 8.9.1 AL 8.11.1</th>
            <th>CUMPLIMIENTO A LOS APARTADOS 8.12 al 8.17.4</th>
            <th>CUMPLIMIENTO A LOS APARTADOS 8.17.5 AL 8.19.5</th>
            <th>REVISIÓN Y MANTENIMIENTO PLANTA DE LUZ</th>
            <th>REVISIÓN AL COMPRESOR</th>
            <th width="40px"><i class="ti ti-dots-vertical fs-6 text-muted"></i></th>
        </tr>
    </thead>

</table>
</div>
</div>

<!-- Modal Crear Mantenimiento -->
<div
    class="modal fade"
    id="ModalCrearMantenimiento"
    tabindex="-1"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-0 border-0">

            <div class="modal-header modal-colored-header bg-primary">
                <h4 class="text-white">
                    <i class="ti" :class="mode=== 'create' ? 'ti-report' :'ti-edit'"></i>
                <span x-show="mode == 'create'">
                    Nuevo reporte
                </span>

                <span x-show="mode == 'edit'">
                    Editar reporte
                </span>
            </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body">

                <div class="form-label">
     
                        * Fecha:
                </div>

                <input
                    type="date"
                    class="form-control "
                    x-model="form.fecha"
                    @change="errors.fecha = false"
                    :class="errors.fecha ? 'is-invalid' : ''">

                <div class="border-top"></div>

                <template
                    x-for="(formato,index) in formatos"
                    :key="index">

                    <div>

                        <div class="pt-1 pb-1 mt-2 form-label">

                            <a
                                :href="formato.template"
                                target="_blank">

                                 <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                            

                            <span
                                x-text="formato.nombre">
                            </span>

                            </a>

                        </div>

                        <input
                            type="file"
                            accept=".pdf"
                            class="form-control mt-2 mb-3"
                            @change="
                                form.archivos[
                                    formato.campo
                                ] = $event.target.files[0]
                            ">
                    </div>

                </template>

            </div>

            <div class="modal-footer">

             <button
                      class="btn bg-danger-subtle text-danger"
                      data-bs-dismiss="modal">
                        <i class="ti ti-x"></i>
                      Cancelar

                  </button>

               <button
                type="button"
                class="btn btn-success"
                @click="submit()">
                <i class="ti ti-check"></i>
                <span x-show="mode == 'create'">
                    Guardar
                </span>

                <span x-show="mode == 'edit'">
                    Actualizar
                </span>

            </button>

            </div>

        </div>
    </div>
</div>

</div>
<?php endif; ?>
