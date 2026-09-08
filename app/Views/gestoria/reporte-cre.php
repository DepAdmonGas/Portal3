<div
    id="container"
    class="pb-4"
    x-data="{ ...actions(), ...reporteCre(<?= $idEstacion ?>) }"
    data-idestacion="<?= $idEstacion ?>">

    <div
        class="d-flex flex-wrap align-items-end gap-3 mt-3 mb-3">

        <!-- Año -->
        <div style="min-width: 150px;">

            <label
                class="form-label fw-semibold mb-1">
                Año
            </label>

            <select
                class="form-select"
                x-model.number="filtros.year"
                @change="cambiarYear()"
                :disabled="cargando">

                <template
                    x-for="year in years"
                    :key="year">

                    <option
                        :value="year"
                        x-text="year">
                    </option>

                </template>

            </select>

        </div>


        <!-- Mes -->
        <div style="min-width: 190px;">

            <label
                class="form-label fw-semibold mb-1">
                Mes
            </label>

            <select
                class="form-select"
                x-model.number="filtros.mes"
                @change="cambiarMes()"
                :disabled="cargando">

                <template
                    x-for="mes in meses"
                    :key="mes.id">

                    <option
                        :value="mes.id"
                        x-text="mes.nombre">
                    </option>

                </template>

            </select>

        </div>


        <!-- Periodo -->
        <div
            class="text-muted small pb-2"
            x-show="!cargando">

            <span
                x-text="periodoTexto">
            </span>

        </div>

        <!-- Descargar facturas del año -->
        <div
            class="pb-1"
            x-show="
            !cargando &&
            reporte &&
            Number(filtros.mes) === 0"
            x-cloak>

            <button
                type="button"
                class="btn btn-primary"
                @click="descargarFacturasAnual()">

                <i
                    class="ti ti-file-zip me-1">
                </i>

                Descargar facturas del año

            </button>

        </div>

        <!-- Loading -->
        <div
            class="pb-2"
            x-show="cargando">

            <span
                class="spinner-border spinner-border-sm text-primary me-1"
                role="status">
            </span>

            <span class="text-muted small">
                Cargando...
            </span>

        </div>

    </div>


    <!-- ====================================================== -->
    <!-- SIN REPORTE                                            -->
    <!-- ====================================================== -->

    <div
        class="text-center py-5"
        x-show="!cargando && buscado && !reporte"
        x-cloak>

        <i
            class="ti ti-file-off fs-8 text-muted">
        </i>

        <div class="mt-2 text-muted">

            No se encontraron reportes para

            <strong
                x-text="periodoTexto">
            </strong>.

        </div>

    </div>


    <!-- ====================================================== -->
    <!-- REPORTE                                                -->
    <!-- ====================================================== -->

    <div
        x-show="!cargando && reporte"
        x-cloak>


        <!-- ================================================== -->
        <!-- RESUMEN POR PRODUCTO                               -->
        <!-- ================================================== -->

        <div class="row mb-4">

            <template
                x-for="producto in reporte?.productos ?? []"
                :key="producto.numero">

                <div
                    class="col-xl-4 col-lg-4 col-md-6 col-12 mb-3">

                    <div class="card">


                        <!-- ================================== -->
                        <!-- PRODUCTO                           -->
                        <!-- ================================== -->

                        <div
                            class="card-header text-white"
                            :style="
                                `background-color: ${producto.color};`
                            ">

                            <div
                                class="d-flex justify-content-between align-items-center gap-2">

                                <div
                                    class="fw-semibold"
                                    x-text="producto.nombre">
                                </div>


                                <!-- Total documentos -->
                                <span
                                    class="badge bg-white text-dark"
                                    x-show="
                                        (producto.documentos?.length ?? 0) > 0
                                    "
                                    x-text="
                                        producto.documentos?.length ?? 0
                                    ">
                                </span>

                            </div>

                        </div>


                        <!-- ================================== -->
                        <!-- TOTALES                            -->
                        <!-- ================================== -->

                        <div class="card-body p-2">

                            <div class="row">

                                <!-- Venta -->
                                <div class="col-12 col-sm-4">

                                    <div
                                        class="text-muted small mb-1">
                                        Total venta
                                    </div>

                                    <div
                                        class="fw-semibold fs-4"
                                        x-text="
                                            formatearNumero(
                                                producto.total_venta
                                            ) + ' Lt'
                                        ">
                                    </div>

                                </div>


                                <!-- Compra -->
                                <div class="col-12 col-sm-4">

                                    <div
                                        class="text-muted small mb-1">
                                        Total compra
                                    </div>

                                    <div
                                        class="fw-semibold fs-5"
                                        x-text="
                                            formatearNumero(
                                                producto.total_compra
                                            ) + ' Lt'
                                        ">
                                    </div>

                                </div>


                                <!-- Importe -->
                                <div class="col-12 col-sm-4">

                                    <div
                                        class="text-muted small mb-1">
                                        Total importe
                                    </div>

                                    <div
                                        class="fw-semibold fs-5"
                                        x-text="
                                            formatearMoneda(
                                                producto.total_importe
                                            )
                                        ">
                                    </div>

                                </div>

                            </div>


                            <!-- ================================== -->
                            <!-- FACTURAS                           -->
                            <!-- ================================== -->

                            <div
                                class="border-top mt-3 pt-2"
                                x-show="
                                    (producto.documentos?.length ?? 0) > 0
                                ">

                                <div
                                    class="text-muted small fw-semibold mb-2">

                                    <i
                                        class="ti ti-file-invoice me-1">
                                    </i>

                                    Facturas

                                </div>


                                <div
                                    class="d-flex flex-wrap gap-2">

                                    <template
                                        x-for="
                                            documento in producto.documentos ?? []
                                        "
                                        :key="
                                            documento.mes +
                                            '-' +
                                            documento.tipo +
                                            '-' +
                                            documento.archivo
                                        ">

                                        <a
                                            :href="documento.url"
                                            download
                                            class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
                                            :title="
                                                'Descargar ' +
                                                documento.nombre
                                            ">

                                            <i
                                                class="ti ti-download fs-5">
                                            </i>


                                            <!-- Mes -->
                                            <span
                                                x-show="
                                                    Number(
                                                        reporte.mes
                                                    ) === 0
                                                "
                                                x-text="
                                                    nombreMes(
                                                        documento.mes
                                                    )
                                                ">
                                            </span>


                                            <!-- Tipo -->
                                            <span
                                                x-text="
                                                    documento.nombre
                                                ">
                                            </span>

                                        </a>

                                    </template>

                                </div>

                            </div>


                            <!-- Sin facturas -->
                            <div
                                class="border-top mt-3 pt-2 text-muted small"
                                x-show="
                                    (producto.documentos?.length ?? 0) === 0
                                ">

                                <i
                                    class="ti ti-file-off me-1">
                                </i>

                                Sin facturas

                            </div>

                        </div>

                    </div>

                </div>

            </template>

        </div>


        <!-- ================================================== -->
        <!-- SIN MOVIMIENTOS                                    -->
        <!-- ================================================== -->

        <div
            class="text-center text-muted py-5"
            x-show="
                reporte &&
                (reporte.dias?.length ?? 0) === 0
            ">

            <i
                class="ti ti-database-off fs-8">
            </i>

            <div class="mt-2">

                No hay movimientos registrados para este periodo.

            </div>

        </div>


        <!-- ================================================== -->
        <!-- DÍAS                                               -->
        <!-- ================================================== -->

        <template
            x-for="dia in reporte?.dias ?? []"
            :key="dia.fecha">

            <div class="card">


                <!-- ========================================== -->
                <!-- ENCABEZADO DEL DÍA                         -->
                <!-- ========================================== -->

                <div class="card-header">

                    <div
                        class="d-flex justify-content-between align-items-center">

                        <div
                            class="d-flex align-items-center gap-2">

                            <i
                                class="ti ti-calendar-event">
                            </i>

                            <strong>

                                Día

                                <span
                                    x-text="dia.dia">
                                </span>

                            </strong>

                        </div>


                        <!-- Mensajes -->
                        <div>

                            <button
                                type="button"
                                class="btn btn-sm btn-light position-relative"
                                @click="abrirChat(dia)">

                                <i
                                    class="ti ti-messages fs-6">
                                </i>

                                <span
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary fs-1"
                                    x-show="
                                        Number(
                                            dia.total_mensajes
                                        ) > 0
                                    "
                                    x-text="
                                        dia.total_mensajes
                                    ">
                                </span>

                            </button>

                        </div>

                    </div>

                </div>


                <!-- ========================================== -->
                <!-- PRODUCTOS DEL DÍA                          -->
                <!-- ========================================== -->

                <div class="card-body p-2">

                    <div class="row">

                        <template
                            x-for="
                                producto in dia.productos ?? []
                            "
                            :key="producto.id">

                            <div
                                class="col-xl-4 col-lg-6 col-md-12 col-12">

                                <div class="card">


                                    <!-- Producto -->
                                    <div
                                        class="card-header text-white"
                                        :style="
                                            `background-color: ${producto.color};`
                                        ">

                                        <strong
                                            x-text="
                                                producto.producto
                                            ">
                                        </strong>

                                    </div>


                                    <div
                                        class="card-body p-2">


                                        <!-- ================== -->
                                        <!-- VOLÚMENES          -->
                                        <!-- ================== -->

                                        <div
                                            class="table-responsive">

                                            <table
                                                class="table table-sm table-bordered align-middle">

                                                <thead>

                                                    <tr>

                                                        <th
                                                            class="text-center">
                                                            Vo. inicial
                                                        </th>

                                                        <th
                                                            class="text-center">
                                                            Vo. venta
                                                        </th>

                                                        <th
                                                            class="text-center">
                                                            Vo. final
                                                        </th>

                                                        <th
                                                            class="text-center">
                                                            Merma
                                                        </th>

                                                    </tr>

                                                </thead>


                                                <tbody>

                                                    <tr>

                                                        <td
                                                            class="text-center"
                                                            x-text="
                                                                formatearNumero(
                                                                    producto.volumen_inicial
                                                                )
                                                            ">
                                                        </td>

                                                        <td
                                                            class="text-center"
                                                            x-text="
                                                                formatearNumero(
                                                                    producto.volumen_venta
                                                                )
                                                            ">
                                                        </td>

                                                        <td
                                                            class="text-center"
                                                            x-text="
                                                                formatearNumero(
                                                                    producto.volumen_final
                                                                )
                                                            ">
                                                        </td>

                                                        <td
                                                            class="text-center text-danger fw-semibold"
                                                            x-text="
                                                                formatearNumero(
                                                                    producto.merma
                                                                )
                                                            ">
                                                        </td>

                                                    </tr>

                                                </tbody>

                                            </table>

                                        </div>


                                        <!-- ================== -->
                                        <!-- PIPAS              -->
                                        <!-- ================== -->

                                        <div
                                            class="table-responsive">

                                            <table
                                                class="table table-sm table-bordered table-striped align-middle pb-0 mb-0">

                                                <thead>

                                                    <tr>

                                                        <th
                                                            class="text-center">
                                                            Pipa
                                                        </th>

                                                        <th
                                                            class="text-center">
                                                            Volumen
                                                        </th>

                                                        <th
                                                            class="text-center">
                                                            Precio litro
                                                        </th>

                                                        <th
                                                            class="text-center">
                                                            Flete
                                                        </th>

                                                        <th>
                                                            Razón social
                                                        </th>

                                                    </tr>

                                                </thead>


                                                <tbody>

                                                    <!-- Sin pipas -->
                                                    <tr
                                                        x-show="
                                                            !producto.pipas ||
                                                            producto.pipas.length === 0
                                                        ">

                                                        <td
                                                            colspan="5"
                                                            class="text-center text-muted">

                                                            No hay pipas registradas.

                                                        </td>

                                                    </tr>


                                                    <!-- Pipas -->
                                                    <template
                                                        x-for="
                                                            pipa in producto.pipas ?? []
                                                        "
                                                        :key="pipa.id">

                                                        <tr>

                                                            <td
                                                                class="text-center"
                                                                x-text="
                                                                    pipa.pipa_numero
                                                                ">
                                                            </td>

                                                            <td
                                                                class="text-end"
                                                                x-text="
                                                                    formatearNumero(
                                                                        pipa.volumen
                                                                    )
                                                                ">
                                                            </td>

                                                            <td
                                                                class="text-end"
                                                                x-text="
                                                                    formatearMoneda(
                                                                        pipa.precio_litro
                                                                    )
                                                                ">
                                                            </td>

                                                            <td
                                                                class="text-end"
                                                                x-text="
                                                                    formatearMoneda(
                                                                        pipa.costo_flete
                                                                    )
                                                                ">
                                                            </td>

                                                            <td
                                                                x-text="
                                                                    pipa.nombre_razonsocial
                                                                    || 'S/I'
                                                                ">
                                                            </td>

                                                        </tr>

                                                    </template>

                                                </tbody>


                                                <tfoot>

                                                    <tr
                                                        class="fw-semibold">

                                                        <td>
                                                            Total
                                                        </td>

                                                        <td
                                                            class="text-end"
                                                            x-text="
                                                                formatearNumero(
                                                                    producto.total_compra
                                                                )
                                                            ">
                                                        </td>

                                                        <td
                                                            class="text-end"
                                                            x-text="
                                                                formatearMoneda(
                                                                    producto.total_importe
                                                                )
                                                            ">
                                                        </td>

                                                        <td
                                                            colspan="2">
                                                        </td>

                                                    </tr>

                                                </tfoot>

                                            </table>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </template>

                    </div>

                </div>

            </div>

        </template>

    </div>


    <!-- ====================================================== -->
    <!-- OFFCANVAS CHAT                                         -->
    <!-- ====================================================== -->

    <div
        class="offcanvas offcanvas-end"
        tabindex="-1"
        id="offcanvasReporteChat"
        aria-labelledby="offcanvasReporteChatLabel"
        style="width: 420px;">


        <!-- Header -->
        <div
            class="offcanvas-header border-bottom">

            <div>

                <h5
                    class="offcanvas-title mb-1"
                    id="offcanvasReporteChatLabel">

                    <i
                        class="ti ti-messages me-1">
                    </i>

                    Mensajes del reporte

                </h5>


                <div
                    class="text-muted small"
                    x-show="chat.dia">

                    <span
                        x-text="
                            chat.dia
                                ? 
                                    chat.dia.dia
                                
                                : ''
                        ">
                    </span>

                </div>

            </div>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="offcanvas"
                aria-label="Cerrar">
            </button>

        </div>


        <!-- Body -->
        <div
            class="offcanvas-body d-flex flex-column p-0"
            style="height: calc(100vh - 73px);">


            <!-- ============================================== -->
            <!-- MENSAJES                                      -->
            <!-- ============================================== -->

            <div
                class="flex-grow-1 overflow-auto p-3"
                x-ref="chatMensajes">


                <!-- Cargando -->
                <div
                    class="text-center py-5"
                    x-show="chat.cargando">

                    <div
                        class="spinner-border spinner-border-sm text-primary mb-2">
                    </div>

                    <div
                        class="text-muted small">

                        Cargando mensajes...

                    </div>

                </div>


                <!-- Sin mensajes -->
                <div
                    class="text-center text-muted py-5"
                    x-show="
                        !chat.cargando &&
                        chat.mensajes.length === 0
                    ">

                    <i
                        class="ti ti-message-off fs-7 d-block mb-2">
                    </i>

                    No hay mensajes para este reporte.

                </div>


                <!-- Lista -->
                <template
                    x-for="
                        mensaje in chat.mensajes
                    "
                    :key="mensaje.id">

                    <div class="mb-3">

                        <div
                            class="d-flex"
                            :class="
                                mensaje.es_mio
                                    ? 'justify-content-end'
                                    : 'justify-content-start'
                            ">

                            <div
                                class="rounded-3 px-3 py-2"
                                style="max-width: 80%;"
                                :class="
                                    mensaje.es_mio
                                        ? 'bg-primary text-white'
                                        : 'bg-light'
                                ">


                                <!-- Usuario -->
                                <div
                                    class="small fw-semibold mb-1"
                                    x-show="
                                        !mensaje.es_mio
                                    "
                                    x-text="
                                        mensaje.usuario
                                        ?? 'Usuario'
                                    ">
                                </div>


                                <!-- Mensaje -->
                                <div
                                    style="
                                        white-space: pre-wrap;
                                    "
                                    x-text="
                                        mensaje.mensaje
                                    ">
                                </div>


                                <!-- Fecha -->
                                <div
                                    class="small mt-1"
                                    :class="
                                        mensaje.es_mio
                                            ? 'text-white-50'
                                            : 'text-muted'
                                    "
                                    x-text="
                                        mensaje.fecha_formateada
                                        ?? mensaje.fecha
                                        ?? ''
                                    ">
                                </div>

                            </div>

                        </div>

                    </div>

                </template>

            </div>


            <!-- ============================================== -->
            <!-- FORMULARIO                                    -->
            <!-- ============================================== -->

            <div
                class="border-top p-3">

                <form
                    @submit.prevent="
                        enviarMensaje()
                    ">

                    <div
                        class="d-flex align-items-end gap-2">

                        <div
                            class="flex-grow-1">

                            <textarea
                                class="form-control"
                                rows="1"
                                placeholder="Escribe un mensaje..."
                                x-model.trim="
                                    chat.mensaje
                                "
                                @keydown.enter="
                                    if (!$event.shiftKey) {
                                        $event.preventDefault();
                                        enviarMensaje();
                                    }
                                "
                                :disabled="
                                    chat.enviando
                                ">
                            </textarea>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="
                                chat.enviando ||
                                !chat.mensaje.trim()
                            ">

                            <span
                                x-show="
                                    !chat.enviando
                                ">

                                <i
                                    class="ti ti-send">
                                </i>

                            </span>

                            <span
                                x-show="
                                    chat.enviando
                                ">

                                <span
                                    class="spinner-border spinner-border-sm">
                                </span>

                            </span>

                        </button>

                    </div>


                    <div
                        class="text-muted small mt-2">

                        Enter para enviar · Shift + Enter para nueva línea

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>