<div id="container"
     x-data="{...actions(),...cursos()}"
     class="container-fluid pb-4">



    <!-- ======================================= -->
    <!-- Pendientes -->
    <!-- ======================================= -->

<template x-if="!loading && cursos.length">

<div class="card shadow-sm border-primary mt-4">

    <div class="card-header bg-primary-subtle">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>Cursos pendientes</strong>
            </div>
            <span class="badge bg-primary text-white"
                  x-text="total">
            </span>
        </div>
    </div>

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <tbody>

                <template
                    x-for="curso in cursos"
                    :key="curso.id">

                    <tr>

                        <td width="200">

                            <small class="text-muted fs-3">
                                <i class="ti ti-calendar"></i>
                                <span
                                    x-text="curso.fecha">
                                </span>
                            </small>

                        </td>

                        <td>
                            <small class="text-muted">Tema:</small>

                            <div class="fs-5"
                                x-text="curso.titulo">
                            </div>

                        </td>

                        <td width="170">
                            <span
                                class="badge bg-light text-dark border"
                                x-text="curso.categoria">
                            </span>

                        </td>

                        <td width="140"
                            class="text-end">

                            <button

                                class="btn btn-success btn-sm"

                                @click="iniciar(curso)">

                                <i class="ti ti-player-play"></i>

                                Iniciar

                            </button>

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

    </div>

</div>

</template>


    <!-- ======================================= -->
    <!-- Módulos -->
    <!-- ======================================= -->

<div>

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h5 class="fs-6">
                    Módulos
                </h5>

            </div>

            <span class="badge text-bg-light border">
                <span x-text="modulos.length"></span>
                módulos
            </span>

        </div>

    <div class="mt-3">

        <div class="row g-3">

            <template
                x-for="modulo in modulos"
                :key="modulo.id">

                <div class="col-xl-4 col-lg-6">

                    <div
                        class="card border h-100 shadow-sm modulo-card p-2"
                        @click="detalle(modulo)"
                        style="cursor:pointer; transition:.25s;">
                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start">

                                <div
                                    class="rounded-circle bg-primary text-white fw-bolder fs-5 d-flex align-items-center justify-content-center"

                                    style="width:52px;height:52px;">

                                    <span
                                        x-text="modulo.numero">
                                    </span>

                                </div>

                                <span
                                    class="badge  bg-secondary-subtle text-secondary"

                                    x-text="modulo.totalTemas + ' temas'">
                                </span>

                            </div>

                            <div class="mt-4">

                                <h6
                                    class="fw-bolder fs-6 text-muted mb-2"
                                    x-text="modulo.titulo">
                                </h6>

                                <div class="d-flex justify-content-between align-items-center">

                                    <small class="text-muted">

                                        Explorar temas del módulo

                                    </small>

                                    <i class="ti ti-arrow-right text-primary fs-7"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </template>

        </div>

    </div>

</div>

</div>