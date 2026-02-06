

<div class="datatables">
    <div class="">
    <div class="">
    <h4 class="">Usuarios</h4>

    <?= (!empty($idestacion))? '<h5 class="">'.$razonsocial.'</h5>' : ''; ?>

    <div class="d-flex flex-wrap gap-6">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-filter fs-4"></i> Estación
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li><a class="dropdown-item" href="javascript:void(0)">Palo Solo</a></li>
                <li>
                    <a class="dropdown-item" href="javascript:void(0)">Interlomas</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="table-responsive mt-4">
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