<div
    id="container"
    x-data="{
        ...actions(),
        ...solicitudCheques()
    }">

    <div class="datatables mt-3">
<div class="d-flex justify-content-between align-items-center gap-3 mb-4">
    <!-- Elemento Izquierdo: Barra de Progreso (oculta por defecto) -->
    <div
        id="firmar-todas-progress"
        class="d-none align-items-center gap-2 border rounded-3 bg-white shadow-sm px-3 py-2"
        role="status"
        aria-live="polite">
        <span class="d-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width: 34px; height: 34px;">
            <i class="ti ti-clock fs-5"></i>
        </span>
        <div class="me-1" style="min-width: 150px;">
            <div id="firmar-todas-progress-title" class="small fw-semibold text-dark">Preparando firmas</div>
            <div class="progress mt-1" style="height: 4px;">
                <div id="firmar-todas-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;"></div>
            </div>
        </div>
        <span id="firmar-todas-timer" class="badge rounded-pill bg-primary-subtle text-primary"></span>
        <span id="firmar-todas-step" class="small text-muted text-nowrap"></span>
    </div>

    <!-- Elemento Derecho: Botón (Siempre a la derecha gracias a ms-auto) -->
    <button
        id="btn-firmar-todas"
        type="button"
        class="btn bg-primary-subtle text-primary d-none ms-auto"
        disabled>
        <i class="ti ti-writing fs-5 me-1"></i>
        <span class="firmar-todas-label">Firmar todas las solicitudes</span>
        <span id="firmar-todas-count" class="badge bg-success text-white ms-1"></span>
    </button>
</div>

        <div class="table-responsive">

            <table
                id="table-cheques"
                class="table table-striped table-bordered mb-0 align-middle w-100"
                style="font-size: .82rem;">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Fecha
                        </th>

                        <th>
                            Beneficiario
                        </th>

                        <th>
                            Monto
                        </th>

                        <th>
                            No. Factura
                        </th>

                        <th>
                            Concepto
                        </th>

                        <th class="text-center">

                            <i
                                class="ti ti-writing text-primary fs-8"
                                title="Firma"></i>

                        </th>

                        <th>
                            Solicitante
                        </th>

                        <th>
                            Razón Social
                        </th>

                    </tr>

                </thead>

                <tbody></tbody>

            </table>

        </div>

    </div>


    <!-- Indicador guardando -->
    <div
        class="position-fixed bottom-0 end-0 m-4"
        style="z-index: 1080;"
        x-show="saving"
        x-cloak>

        <div
            class="bg-white border rounded-3 shadow-sm px-3 py-2 d-flex align-items-center gap-2">

            <span
                class="spinner-border spinner-border-sm text-primary"></span>

            <small class="fw-semibold">
                Guardando...
            </small>

        </div>

    </div>

</div>
