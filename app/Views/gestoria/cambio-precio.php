<div
    id="container"
    class="pb-4"
    x-data="{ ...actions(), ...cambioPrecio(<?= $idEstacion ?>)}" data-idestacion="<?= $idEstacion ?>">

    <div class="datatables mt-4">

        <div class="table-responsive">

            <table
                id="table-cambio-precio"
                class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                <thead>

                    <tr>

                        <th class="text-center">
                            #
                        </th>

                        <th class="text-center">
                            Creación
                        </th>

                        <th class="text-center">
                            Fecha de aplicación
                        </th>

                        <th class="text-center">
                            Hora de aplicación
                        </th>

                        <th class="text-center">
                            G Super
                        </th>

                        <th class="text-center">
                            G Premium
                        </th>

                        <th class="text-center">
                            G Diesel
                        </th>

                        <th class="text-center">
                            <a class="text-muted">
                                <i
                                    class="ti ti-dots-vertical fs-6">
                                </i>
                            </a>
                        </th>

                    </tr>

                </thead>

                <tbody></tbody>

            </table>

        </div>

    </div>

</div>