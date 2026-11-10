<div id="container" data-elemento="110" data-herramienta="2" data-id="0">

    
    <div class="datatables mt-3">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
            <table id="table-auditorias-sgm" class="table table-striped table-bordered align-middle mb-4">
                    <thead>
                        <tr>
                            <th class="text-center" width="96px">#</th>
                            <th class="text-center">Año</th>

                            <th colspan="2" class="text-center">
                                Fo.SGM.018 Plan de Auditoria
                            </th>

                            <th colspan="2" class="text-center">
                                Fo.SGM.019 Reporte e Hallazgos de Auditoria
                            </th>

                            <th colspan="2" class="text-center">
                                Fo.SGM.020 Plan de atencion de Hallazgos
                            </th>
                        </tr>
                    </thead>

                    <tbody></tbody>

                </table>
        </div>
    </div>
     
 

    <div class="row">
        <div class="col-md-6">

            <div class="card">
                <div x-data="{ ...actions(), ...listaasistenciaForm() }">
                    <div class="card-header">
                       
               <div class="row align-items-center">

    <div class="col-md-9">
             <h4 class="card-title  mb-0">Fo.SGM.001 Lista de asistencia</h4>
    </div>

    <div class="col-12 col-md-3 d-grid text-end mt-3 mt-md-0">
           <?=
                            !empty($permisos['crear']) ?
                                '<button type="button" class="btn bg-primary-subtle text-primary" @click="crearAsistencia()">
          <i class="ti ti-plus"></i> Nuevo
          </button>'
                                : ''
                            ?>
    </div>

</div>


                    </div>
                    <div class="card-body">
                        <div class="datatables">
                            <div class="table-responsive overflow-x-auto overflow-y-hidden p-1">
                                <table id="table-lista-asistencia" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
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
            </div>

        </div>
    </div>

    <!-- ------------------------- -->
    <!-- inicio offcanvas -------- -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasExampleLabel">
                Ayuda
            </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body fs-4">

            <p>Una vez cumplido el primer año de implementación de tu SGM se te activara el presente elemento para que de manera anual se realice la auditoria interna o externa. Recuerda realizar el registro mediante los formatos 017, 018, 019, 001</p>

        </div>
    </div>
    <!-- ------------------------- -->
    <!-- fin offcanvas -------- -->

</div>
