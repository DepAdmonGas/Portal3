<div id="container" class="mt-4 mb-5"
     data-id-reporte="<?= $idReporte ?>"
     x-data="{ ...actions(), ...programarHorarioDetalleComponent() }">

    <div id="programar-horario-detalle-loading" class="text-center py-5" x-show="cargando">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

<template x-if="reporte">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-end align-items-center gap-2">
            <span class="badge rounded-pill "
                  :class="reporte.estado == 1 ? 'text-bg-success' : 'text-bg-danger'">
                <span x-text="reporte.estado == 1 ? 'Finalizado' : 'Pendiente'"></span>
            </span>

            <span class="badge rounded-pill text-bg-primary ">
                <i class="ti ti-calendar me-1"></i>
                <span x-text="reporte.fecha"></span>
            </span>

        </div>
    </div>
</template>

    <template x-if="secciones.length > 0">
        <div>
            <template x-for="(seccion, idx) in secciones" :key="idx">
                <div class="mb-4">
                    <div class="datatables">
                        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
                            <table :id="'ph-detalle-table-' + idx" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
                                <thead>
                                    <tr>
                                        <th class="text-center align-middle" width="48">#</th>
                                        <th class="text-start align-middle">Nombre completo</th>
                                        <th class="text-center align-middle">Puesto</th>
                                        <th class="text-center align-middle">Lunes</th>
                                        <th class="text-center align-middle">Martes</th>
                                        <th class="text-center align-middle">Miércoles</th>
                                        <th class="text-center align-middle">Jueves</th>
                                        <th class="text-center align-middle">Viernes</th>
                                        <th class="text-center align-middle">Sábado</th>
                                        <th class="text-center align-middle">Domingo</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <template x-if="!cargando && secciones.length === 0">
        <div class="text-center text-muted py-5">
            <i class="ti ti-info-circle fs-1"></i>
            <p class="mt-2">No se encontró información para mostrar.</p>
        </div>
    </template>

</div>
