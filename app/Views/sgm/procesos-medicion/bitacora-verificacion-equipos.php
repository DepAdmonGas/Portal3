<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...bitacoraVerificacion() }">

    <div class="datatables mt-4">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
            <table class="table table-bordered align-middle" id="table-bitacora-verificacion-equipos">
                <thead>
                    <tr class="bg-primary text-white">
                        <th class="text-center">Equipo a calibrar</th>
                        <th class="text-center">Periodicidad</th>
                        <th class="text-center">Fechas programadas</th>
                        <th class="text-center">Estatus</th>
                        <th class="text-center align-middle" width="35px">
                            <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    <div
        class="modal fade"
        id="modalDetalleBitacora"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-xl modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white">
                        <i class="ti ti-eye"></i>
                        Detalle de bitácora de verificación
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">

                    <template x-if="programa.equipo?.nombre == 'Sensor de nivel y temperatura'">
                        <div>

<!-----------inicio de la primera card detalle------->
                            <div class="card">
                                <div class="card-header bg-primary">
                                    <h5 class="card-title text-white mb-0">
                                        <i class="ti ti-settings-cog"></i>
                                Sensor de nivel y temperatura
                            </h5>
                                </div>

                            <div class="card-body p-0">
                                <table class="table table-striped table-bordered  align-middle mb-0">
 <tbody>
  <tr>
                                        <td width="700" class="align-middle">
                                        Fecha:
                                        </td>

                                        <td
                                        class="text-center"
                                         x-text="bitacora.fecha"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                        Hora:
                                        </td>

                                        <td
                                        class="text-center" 
                                        x-text="bitacora.hora"></td>
                                    </tr>

 </tbody>

                                  
                                </table>

                               
                            </div>

                            </div>

<!------------inicio de la segunda card detalle---------->
<div class="card">
<div class="card-header bg-primary">
<h5 class="card-title text-white mb-0">
    <i class="ti ti-progress-check"></i>
                                Verificación de sensores de nivel y temperatura
                            </h5>
</div>
<div class="card-body p-0">
<table class="table table-striped table-bordered  align-middle mb-0">
<tbody>
                                    <tr>
                                        <td class="align-middle">
                                        No. de tanque
                                        </td>

                                        <td 
                                        class="text-center"
                                        x-text="bitacora.no_tanque"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                        Marca
                                        </td>

                                        <td 
                                        class="text-center"
                                        x-text="bitacora.marca"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                        Capacidad
                                        </td>

                                        <td 
                                        class="text-center"
                                        x-text="bitacora.capacidad"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                        Producto que almacena
                                        </td>

                                        <td 
                                        class="text-center"
                                        x-text="bitacora.producto"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                        
                                                La verificación es realizada por personal interno o
                                                externo (en caso de ser externo indicar la empresa).
                                            
                                        </td>

                                        <td 
                                        class="text-center"
                                        x-text="bitacora.interno_externo"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                        
                                                Al iniciar la calibración se asegura que el producto se
                                                encuentre sin movimiento
                                            
                                        </td>

                                        <td 
                                        class="text-center"
                                        x-text="bitacora.verificacion_movimiento"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                        
                                                Método para determinar el nivel líquido dentro del tanque
                                                (Inmersión o medida seca)
                                            
                                        </td>

                                        <td 
                                        class="text-center"
                                        x-text="bitacora.metodo_nivel"></td>
                                    </tr>
</tbody>
                            </table>
</div>
</div>
                            
 

                            <!---------tercera y utima card---->
                    <template
                                x-for="categoria in resultados"
                                :key="categoria.titulo">

                                <div class="mb-4">
                                    <div class="card">
                                        <div class="card-header bg-primary">
<span class="card-title text-white" x-text="categoria.titulo"></span>
                                        </div>
                
                                        <div class="card-body p-0">

                                    <table class="table table-striped table-bordered  align-middle mb-0">

                                        <thead>

                                            <tr>

                                                
                                                    <th>
                                                    pregunta
                                                </th>

                                                <th class="text-center" width="250">
                                                    Resultado
                                                </th>

                                            </tr>

                                        </thead>

                                        <tbody>

                                            <template
                                                x-for="item in categoria.preguntas"
                                                :key="item.id">

                                                <tr>

                                                    <td x-text="item.lista.pregunta"></td>

                                                    <td
                                                    class="text-center" 
                                                    x-text="item.resultado"></td>

                                                </tr>

                                            </template>

                                        </tbody>

                                    </table>
                                        </div>
                                    </div>


                                </div>

                            </template>

                            <div class="alert alert-warning text-black mt-4">

                                <b>Nota 1:</b>

                                Referente al nivel puede existir una variación de ±3 mm; para
                                aplicaciones fiscales o transferencia de custodia los equipos deben
                                cumplir con un EMP de ±4 mm en todo el intervalo de medición.
<div class="mt-2">
    <b>Nota 2:</b>

                                Referente a la temperatura puede existir una variación igual o menor
                                a 0.5 °C.
</div>
                                

                            </div>

                        </div>
                    </template>

                    <!-- Dispensarios -->
                    <template x-if="programa.equipo?.nombre == 'Dispensarios'">
                        <div>
                            <h5 class="mb-3 mt-3">Dispensarios</h5>

                            <table class="table table-sm table-bordered align-middle mb-4 fs-4">
                                <tbody>

                                    <tr>
                                        <td width="45%">
                                            <strong>Fecha</strong>
                                        </td>
                                        <td x-text="bitacora.fecha"></td>
                                    </tr>

                                    <tr>
                                        <td>
                                            <strong>Hora</strong>
                                        </td>
                                        <td x-text="bitacora.hora"></td>
                                    </tr>

                                    <tr>
                                        <td colspan="2" class="bg-muted text-white">
                                            <strong>Verificacion de dispensarios</strong>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="table-light">
                                            1. Aspecto a verificar en los patrones de referencia
                                        </td>
                                        <td class="table-light">Resultado</td>
                                    </tr>

                                    <tr>
                                        <td>
                                            Marca y modelo de la jarra patrón
                                        </td>
                                        <td x-text="bitacora.marca_modelo_jarra_patron"></td>
                                    </tr>

                                    <tr>
                                        <td>
                                            Capacidad
                                        </td>
                                        <td x-text="bitacora.capacidad"></td>
                                    </tr>

                                    <tr>
                                        <td>
                                            La jarra patrón se encuentra calibrada
                                        </td>
                                        <td x-text="bitacora.jarra_patron_calibrada"></td>
                                    </tr>

                                    <tr>
                                        <td class="bg-light">
                                            2. Aspecto a verificar
                                        </td>
                                        <td class="table-light">Resultado</td>
                                    </tr>

                                    <tr>
                                        <td>
                                            No. de dispensario
                                        </td>
                                        <td x-text="bitacora.no_dispensario"></td>
                                    </tr>

                                </tbody>
                            </table>

                            <table class="table table-sm table-bordered table-hover align-middle">

                                <thead class="table-light">

                                    <tr>

                                        <th>Lado</th>

                                        <th>Producto</th>

                                        <th>Medida a comparar (ml)</th>

                                        <th>Medición jarra patrón (ml)</th>

                                        <th>Diferencia</th>

                                        <th>Resultado</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <template
                                        x-if="detalles.length===0">

                                        <tr>

                                            <td
                                                colspan="7"
                                                class="text-center text-muted">

                                                No hay registros

                                            </td>

                                        </tr>

                                    </template>

                                    <template
                                        x-for="detalle in detalles"
                                        :key="detalle.id">

                                        <tr>

                                            <td x-text="detalle.lado"></td>

                                            <td x-text="detalle.producto"></td>

                                            <td x-text="detalle.medida_comparar + ' ml'"></td>

                                            <td x-text="detalle.medicion_jarra_patron + ' ml'"></td>

                                            <td
                                                x-text="detalle.diferencia + ' ml'">
                                            </td>

                                            <td>

                                                <span
                                                    class="badge"
                                                    :class="detalle.resultado=='Favorable'
                                    ? 'bg-success'
                                    : 'bg-danger'"
                                                    x-text="detalle.resultado">

                                                </span>

                                            </td>

                                        </tr>

                                    </template>

                                </tbody>

                            </table>

                        </div>
                    </template>

                </div>
                <div class="modal-footer">
   <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cerrar

                </button>

                </div>
             
            </div>

        </div>
    </div>

</div>

<?php endif; ?>

</div>