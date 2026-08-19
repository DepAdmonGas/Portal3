<div id="container"
x-data="{ ...actions(), ...atencionHallazgos()}">

    <div class="text-end">
        <?= 
            !empty($permisos['crear']) ? 
            '<button type="button" class="btn btn-primary" @click="nuevo()">
            <i class="ti ti-plus"></i> Nuevo
            </button>' 
            : '' 
            ?>     
    </div>

    <table class="table table-bordered table-sm mt-2 mb-2">
    <tr>
    <td class="text-center align-middle"><img class="text-center" src="<?= asset('images/logos/Logo.png') ?>" style="width: 200px;"></td>
    <td colspan="2" class="text-center align-middle"><b>Atención de Hallazgos</b></td>
    <td class="text-center align-middle">Fo.ADMONGAS.018</td>
    </tr>
    <tr>
    <td class="text-center align-middle">Realizado por: Nelly Estrada Garcia </td>
    <td class="text-center align-middle">Revisado por: Eduardo Galicia Flores </td>
    <td class="text-center align-middle">Autorizado por: Tomas Tarno Quinzaños </td>
    <td class="text-center align-middle">Fecha de autorizacion 01/10/2018</td>
    </tr>
    </table>

    <table class="table table-sm table-striped table-bordered mb-0 text-nowrap align-middle mt-3">
        <thead>
          <tr>
           <th class="bg-primary text-white text-center">#</th>
            <th class="bg-primary text-white text-center">Fecha de la auditoria</th>
            <th class="bg-primary text-white text-center">No de control de la auditoria</th>
            <th class="bg-primary text-white text-center">Tipo de auditoria</th>
          <th class="bg-primary text-white text-center">
          <a><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody>
                    <template
                    x-for="registro in registros"
                    :key="registro.id">

                    <tr>

                        <td
                            class="text-center fw-bold"
                            x-text="registro.numero">
                        </td>

                        <td
                            class="text-center"
                            x-text="registro.fecha_larga">
                        </td>

                        <td
                            class="text-center"
                            x-text="registro.no_control">
                        </td>

                        <td
                            class="text-center"
                            x-text="registro.tipo_auditoria">
                        </td>

                        <td class="text-center">

                        <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">

                                <li>                            
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3"
                                    href="javascript:void(0)"
                                    @click="editar(registro.id)">
                                        <i class="ti ti-edit"></i> Editar
                                    </a>
                                </li>

                                <li>                            
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3"
                                    :href="`/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/pdf/${registro.id}`"target="_blank">
                                        <i class="ti ti-download"></i> Descargar
                                    </a>
                                </li>

                                <li>                            
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3"
                                    href="javascript:void(0)"
                                    @click="eliminar(registro.id)">
                                        <i class="ti ti-trash"></i> Eliminar
                                    </a>
                                </li>
                            </ul>
                        </div>
                        </div>

                        </td>

                    </tr>

        </template>

                <tr
                    x-show="!loading && registros.length === 0">

                    <td
                        colspan="5"
                        class="text-center">

                        <small>
                            No se encontró información para mostrar
                        </small>

                    </td>

                </tr>
        </tbody>
      </table>


</div>