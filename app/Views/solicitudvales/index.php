<div
    id="container"
    class="mb-4"
    x-data="{
        ...actions(),
        ...solicitudVales()
    }">


    <div class="datatables mt-3">

        <div class="table-responsive">

            <table
                id="table-vales"
                class="table table-striped table-bordered mb-0 align-middle w-100"
                style="font-size: .82rem;">

                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Monto</th>
                        <th>Concepto</th>
                        <th>Solicitante</th>
                        <th>Autorizado</th>
                        <th>Método Autorización</th>
                        <th>Razón Social</th>
                        <th>Cuenta</th>
                    </tr>
                </thead>

                <tbody></tbody>

            </table>

        </div>

    </div>


    <!-- Indicador sencillo de edición -->
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