<div class="pb-4" x-data="{ ...actions(), ...editarBitacoraVerificacion(<?= $id ?>) }">


    <template x-if="programa.equipo?.nombre == 'Sensor de nivel y temperatura'">
        <div>

            <h5 class="mb-3 mt-3">
                Sensor de nivel y temperatura
            </h5>

            <table class="table table-bordered table-sm fs-4">

                <tbody>

                    <tr>
                        <td width="700" class="align-middle">
                            <b>Fecha:</b>
                        </td>

                        <td class="p-0">
                            <input
                                type="date"
                                class="form-control border-0"
                                x-model="bitacora.fecha"
                                @change="guardar('fecha')">
                        </td>
                    </tr>

                    <tr>
                        <td class="align-middle">
                            <b>Hora:</b>
                        </td>

                        <td class="p-0">
                            <input
                                type="time"
                                class="form-control border-0"
                                x-model="bitacora.hora"
                                @change="guardar('hora')">
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2" class="bg-muted text-white">
                            <b>Verificación de sensores de nivel y temperatura</b>
                        </td>
                    </tr>

                    <tr>
                        <td class="align-middle">
                            <b>No. de tanque</b>
                        </td>

                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.no_tanque"
                                @change="guardar('no_tanque')">
                        </td>
                    </tr>

                    <tr>
                        <td class="align-middle">
                            <b>Marca</b>
                        </td>

                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.marca"
                                @change="guardar('marca')">
                        </td>
                    </tr>

                    <tr>
                        <td class="align-middle">
                            <b>Capacidad</b>
                        </td>

                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.capacidad"
                                @change="guardar('capacidad')">
                        </td>
                    </tr>

                    <tr>
                        <td class="align-middle">
                            <b>Producto que almacena</b>
                        </td>

                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.producto"
                                @change="guardar('producto')">
                        </td>
                    </tr>

                    <tr>
                        <td class="align-middle">
                            <b>
                                La verificación es realizada por personal interno o
                                externo (en caso de ser externo indicar la empresa).
                            </b>
                        </td>

                        <td class="p-0">
                            <textarea
                                rows="3"
                                class="form-control border-0"
                                x-model="bitacora.interno_externo"
                                @change="guardar('interno_externo')"></textarea>
                        </td>
                    </tr>

                    <tr>
                        <td class="align-middle">
                            <b>
                                Al iniciar la calibración se asegura que el producto se
                                encuentre sin movimiento
                            </b>
                        </td>

                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.verificacion_movimiento"
                                @change="guardar('verificacion_movimiento')">
                        </td>
                    </tr>

                    <tr>
                        <td class="align-middle">
                            <b>
                                Método para determinar el nivel líquido dentro del tanque
                                (Inmersión o medida seca)
                            </b>
                        </td>

                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.metodo_nivel"
                                @change="guardar('metodo_nivel')">
                        </td>
                    </tr>

                </tbody>

            </table>

            <template
                x-for="categoria in resultados"
                :key="categoria.titulo">

                <div class="mb-4">

                    <table class="table table-bordered table-sm fs-4">

                        <thead>

                            <tr>

                                <th class="bg-muted text-white" x-text="categoria.titulo"></th>

                                <th class="bg-muted text-white" width="250">
                                    Resultado
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <template
                                x-for="item in categoria.preguntas"
                                :key="item.id">

                                <tr>

                                    <td
                                        x-text="item.lista.pregunta">
                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="text"
                                            class="form-control border-0"
                                            x-model="item.resultado"
                                            @change="guardarResultado(item)">

                                    </td>

                                </tr>

                            </template>

                        </tbody>

                    </table>

                </div>

            </template>

            <div class="alert alert-light text-black mt-4">

                <b>Nota 1:</b>

                Referente al nivel puede existir una variación de ±3 mm; para
                aplicaciones fiscales o transferencia de custodia los equipos deben
                cumplir con un EMP de ±4 mm en todo el intervalo de medición.

                <hr>

                <b>Nota 2:</b>

                Referente a la temperatura puede existir una variación igual o menor
                a 0.5 °C.

            </div>

            <div class="text-end">

                <button
                    class="btn btn-primary"
                    @click="finalizar()">
                    <i class="ti ti-check"></i>
                    Finalizar bitácora de verificación

                </button>

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
                        <td class="p-0">
                            <input
                                type="date"
                                class="form-control border-0"
                                x-model="bitacora.fecha"
                                @change="guardar('fecha')">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong>Hora</strong>
                        </td>
                        <td class="p-0">
                            <input
                                type="time"
                                class="form-control border-0"
                                x-model="bitacora.hora"
                                @change="guardar('hora')">
                        </td>
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
                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.marca_modelo_jarra_patron"
                                @change="guardar('marca_modelo_jarra_patron')">
                        </td>
                    </tr>

                    <tr>
                        <td>
                            Capacidad
                        </td>
                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.capacidad"
                                @change="guardar('capacidad')">
                        </td>
                    </tr>

                    <tr>
                        <td>
                            La jarra patrón se encuentra calibrada
                        </td>
                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.jarra_patron_calibrada"
                                @change="guardar('jarra_patron_calibrada')">
                        </td>
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
                        <td class="p-0">
                            <input
                                type="text"
                                class="form-control border-0"
                                x-model="bitacora.no_dispensario"
                                @change="guardar('no_dispensario')">
                        </td>
                    </tr>

                </tbody>
            </table>

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="mb-0">
                    Verificación de mangueras
                </h5>

                <button
                    class="btn btn-primary btn-sm"
                    @click="abrirModalDetalle()">

                    <i class="ti ti-plus"></i>
                    Agregar

                </button>

            </div>

            <table class="table table-sm table-bordered table-hover align-middle">

                <thead class="table-light">

                    <tr>

                        <th>Lado</th>

                        <th>Producto</th>

                        <th>Medida a comparar (ml)</th>

                        <th>Medición jarra patrón (ml)</th>

                        <th>Diferencia</th>

                        <th>Resultado</th>

                        <th width="60" class="text-center text-muted"><i class="ti ti-trash fs-6"></i></th>

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

                            <td class="text-center">

                                <a
                                    @click="eliminarDetalle(detalle.id)">
                                    <i class="ti ti-trash text-danger fs-6"></i>

                                </a>

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

            <div class="text-end mt-4">

                <button
                    class="btn btn-success"
                    @click="finalizar()">

                    <i class="ti ti-check"></i>

                    Finalizar bitácora de verificación

                </button>

            </div>
        </div>
    </template>

    <!-- Modal -->

    <div
        class="modal fade"
        id="modalAgregarManguera"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h5 class="modal-title text-white">
                        Agregar verificación de manguera
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Lado
                            </label>

                            <select
                                class="form-select"
                                x-model="nuevo.lado">

                                <option value="">Seleccione...</option>
                                <option value="A">A</option>
                                <option value="B">B</option>

                            </select>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Producto
                            </label>

                            <select
                                class="form-select"
                                x-model="nuevo.producto">

                                <option value="">Seleccione...</option>

                                <?php
                                $productos = [
                                    $user->estacion->producto_uno,
                                    $user->estacion->producto_dos,
                                    $user->estacion->producto_tres
                                ];

                                foreach ($productos as $producto):
                                    if (!empty($producto)): ?>
                                        <option value="<?= htmlspecialchars($producto) ?>">
                                            <?= htmlspecialchars($producto) ?>
                                        </option>
                                <?php endif;
                                endforeach; ?>

                            </select>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Medida a comparar (ml)
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                x-model.number="nuevo.medida_comparar">

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Medición de la jarra patrón (ml)
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                x-model.number="nuevo.medicion_jarra_patron">

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Diferencia
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                :value="`${diferenciaManguera} ml`"
                                readonly>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Resultado
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                :value="resultadoManguera"
                                readonly>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal" @click="resetModal()">
                        <i class="ti ti-x"></i>
                        Cancelar
                    </button>

                    <button
                        type="button"
                        class="btn btn-success"
                        @click="guardarManguera()">

                        <i class="ti ti-check"></i>
                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>