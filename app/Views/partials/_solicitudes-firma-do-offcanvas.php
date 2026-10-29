<style>
    /* Por defecto (móviles / responsive): ocupa el 100% de la pantalla */
    #offcanvasSolicitudesFirma {
        --bs-offcanvas-width: 100vw;
    }

    /* En pantallas medianas y grandes (normal): ocupa el 50% de la pantalla */
    @media (min-width: 768px) {
        #offcanvasSolicitudesFirma {
            --bs-offcanvas-width: 75vw;
        }
    }
</style>

<?php /* Offcanvas global: Solicitudes pendientes (Firma) del sidebar DO. */ ?>
<div class="offcanvas offcanvas-end" tabindex="-1"
     id="offcanvasSolicitudesFirma"
     aria-labelledby="offcanvasSolicitudesFirmaLabel"
     data-bs-scroll="false" data-bs-backdrop="true"
     x-data="solicitudesFirmaDoComponent()" x-init="init()">

    <div class="offcanvas-header bg-primary">
        <div class="hstack gap-3">
            <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
                 style="width:48px; height:48px;">
                <i class="ti ti-signature text-primary fs-7"></i>
            </div>
            <div>
                <h5 class="offcanvas-title text-white mb-0" id="offcanvasSolicitudesFirmaLabel">
                    SOLICITUDES PENDIENTES (FIRMA)
                </h5>
                <small class="text-white opacity-75">
                    Pendientes: <span x-text="total"></span>
                </small>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <div class="offcanvas-body">

        <div class="mb-4">
            <label class="form-label" for="solicitudes-firma-tipo">
                Tipo de solicitud:
            </label>
            <select class="form-select shadow-sm"
                    id="solicitudes-firma-tipo"
                    x-model="tipo"
                    @change="cargar()"
                    :disabled="cargando">
                <template x-for="opcion in tipos" :key="opcion.valor">
                    <option :value="opcion.valor" x-text="opcion.etiqueta"></option>
                </template>
            </select>
        </div>

        <div x-show="cargando">
            <div class="d-flex align-items-center justify-content-center py-5 text-muted">
                <span class="spinner-border spinner-border-sm me-2"></span>
                <span>Cargando solicitudes...</span>
            </div>
        </div>

        <div class="alert alert-danger" x-show="!cargando && error !== ''" x-text="error"></div>

        <div x-show="!cargando && error === ''">
            <div class="d-flex justify-content-end mb-2">
                <span class="text-muted">Mostrando: <span x-text="items.length"></span></span>
            </div>

            <div class="table-responsive border rounded">
                <table class="table table-striped table-bordered mb-0 w-100 align-middle">
                    <thead>
                        <tr>
                            <th class="text-center align-middle">Solicitud / Pedido</th>
                            <th class="text-center align-middle">Fecha</th>
                            <th class="text-start align-middle">Concepto</th>
                            <th class="text-start align-middle">Beneficiario</th>
                            <th class="text-end align-middle">Monto</th>
                            <th class="text-center align-middle"><i class="ti ti-writing-sign fs-8 text-primary"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in items" :key="item.tipo + '-' + item.id">
                            <tr>
                                <td class="text-center align-middle text-nowrap">
                                    <span x-text="item.tipo_label || item.tipo"></span>
                                </td>
                                <td class="text-center align-middle text-nowrap" x-text="item.fecha"></td>
                                <td class="text-wrap" style="min-width: 180px;" x-text="item.concepto"></td>
                                <td class="text-start align-middle" x-text="item.beneficiario || 'N/A'"></td>
                                <td class="text-end text-nowrap" x-text="item.monto !== null ? '$' + item.monto : 'N/A'"></td>
                                <td class="text-center align-middle">
                                <i class="ti ti-writing-sign fs-8 text-primary pointer" class="btn btn-sm bg-primary-subtle text-primary"
                                            :class="item.disponible ? '' : 'opacity-50'"
                                            :title="item.disponible ? ('Firmar ' + item.tipo) : item.aviso"
                                            @click="abrirSolicitud(item)"></i>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="items.length === 0">
                            <td colspan="6" class="text-center py-5">
                                <i class="ti ti-file-off text-muted" style="font-size: 48px;"></i>
                                <p class="text-primary mb-0 mt-2">No se encontrarón solicitudes pendientes</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="border-top p-3 d-flex justify-content-end">
        <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="offcanvas">
           <i class="ti ti-x"></i>  Cerrar
        </button>
    </div>

</div>
