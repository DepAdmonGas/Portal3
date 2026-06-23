<div id="container" class="pb-4"
x-data="programaAuditoria()">

<div class="text-end mt-2">
   <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li>
                    <a class="dropdown-item" @click="abrirModalBuscar()"><i class="ti ti-search"></i> Buscar</a>
                </li>
                 <li>
                    <a class="dropdown-item" @click="descargarPdf()"><i class="ti ti-download"></i> Descargar</a>
                </li>
            </ul>
        </div>
</div>

    <table class="table table-bordered table-sm mt-3">
    <tr>
    <td class="text-center align-middle"><img class="text-center" src="<?= $_ENV['APP_URL'] . '/assets/images/logos/Logo.png'; ?>" style="width: 200px;"></td>
    <td colspan="2" class="text-center align-middle"><b>Formato Programa de auditorias (Internas y externas) </b></td>
    <td class="text-center align-middle">Fo.ADMONGAS.023</td>
    </tr>
    <tr>
    <td class="text-center align-middle">Realizado por: Nelly Estrada Garcia </td>
    <td class="text-center align-middle">Revisado por: Eduardo Galicia Flores </td>
    <td class="text-center align-middle">Autorizado por: <?= $apoderado_legal; ?></td>
    <td class="text-center align-middle">Fecha de autorizacion 01-Oct-2018</td>
    </tr>
    </table>

    <table class="table table-bordered table-sm mt-3">

        <thead>

            <tr>
                <th class="bg-primary text-white">Tipo auditoría</th>
                <th class="bg-primary text-white">Responsable</th>
                <th class="bg-primary text-white">Periodicidad</th>
                <template
                    x-for="year in years"
                    :key="year">

                    <th
                        class="bg-primary text-white text-center"
                        x-text="year">
                    </th>

                </template>

            </tr>

        </thead>

        <tbody>

            <template
                x-for="registro in registros"
                :key="registro.id">

                <tr>

                    <td x-text="registro.tipo_auditoria"></td>

                    <td x-text="registro.responsable"></td>

                    <td x-text="registro.periodicidad"></td>

                    <template
                        x-for="year in years"
                        :key="year">

                        <td
                            class="text-center"
                            :class="{
                                'table-primary':
                                    registro.tipo_auditoria === 'Interna'
                                    && existeEnYear(registro, year),

                                'table-success':
                                    registro.tipo_auditoria === 'Externa'
                                    && existeEnYear(registro, year)
                            }">

                            <span
                                x-show="existeEnYear(registro, year)"
                                x-text="mes(registro.fecha)">
                            </span>

                        </td>

                    </template>

                </tr>

            </template>

        </tbody>

    </table>

    <div class="text-center"><small>*Las auditorias al SA se realizaran por personal interno de la empresa, que puede ser el gerente de la estación de servicio, el Representante legal, el departamento de gestión, entre otras y las auditorias externas se realizaran por un tercer acreditado (cada dos años de acuerdo a las DACG expendio de petrolíferos) ante la Agencia de Seguridad Energía y Ambiente, tercer acreditado que tendrá que tener vigente su autorización ante la Agencia y el personal podrá elegir. </small></div>

    <!-- Modal Buscar -->

    <div
    class="modal fade"
    id="modalBuscar"
    tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Buscar
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <label class="form-label">
                    Fecha inicio
                </label>

                <input
                    type="date"
                    class="form-control"
                    x-model="fechaInicio">

                <label class="form-label mt-3">
                    Fecha término
                </label>

                <input
                    type="date"
                    class="form-control"
                    x-model="fechaFin">

            </div>

            <div class="modal-footer">

             <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                    class="btn btn-primary"
                    @click="buscar()">

                    Buscar

                </button>

            </div>

        </div>

    </div>

</div>

</div>