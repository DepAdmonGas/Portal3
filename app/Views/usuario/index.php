

<div class="datatables">
    <div class="">
    <div class="">
    <h4 class="">Usuarios</h4>

    <?= (!empty($idestacion))? '<h5 class="">'.$razonsocial.'</h5>' : ''; ?>

    <div class="table-responsive">
        <table id="table-usuarios" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Telefono</th>
                <th>Puesto</th>
                <th>Estación</th>
                <th>Estatus</th>
                <th class="text-center">
                <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
                </th>
            </tr>
        </thead>
        <tbody></tbody>
        </table>
    </div>
    </div>              
    </div>
</div>