<div id="container" class="mt-4 mb-5"
    data-id-reporte="<?= $detail['id'] ?>"
    data-year="<?= $detail['year'] ?>"
    data-quincena="<?= $detail['quincena'] ?>"
    data-status="<?= $detail['status'] ?>"
    data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
    data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
    data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
    data-id-usuario="<?= $idUsuario ?>"
    x-data="{ ...actions(), ...diaDobleForm() }"
    x-init="initForm()">

    <?php if ($detail['status'] == 0 && $puedeEditar): ?>
<div class="row mb-3 align-items-center">
    <!-- Columna del botón Finalizar: 100% de ancho en móviles (col-12) y ancho automático en PC (col-md-auto) -->
    <div class="col-12 col-md-auto mb-2 mb-md-0">
        <button type="button" class="btn btn-success w-100" @click="finalizarFormato()" :disabled="guardando">
            <i class="ti ti-check me-1"></i> <span x-text="guardando ? 'Guardando...' : 'Finalizar'"></span>
        </button>
    </div>

    <!-- Columna del Dropdown: Se alinea a la derecha en PC con ms-auto -->
    <div class="col-12 col-md-auto ms-md-auto text-end">
        <div class="dropdown">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item pointer" @click="abrirModalAgregarPersonal()"><i class="ti ti-plus me-1"></i> Nuevo Personal</a></li>
                <li><a class="dropdown-item pointer" @click="abrirModalEditarQuincena()"><i class="ti ti-calendar me-1"></i> Editar Quincena</a></li>
            </ul>
        </div>
    </div>
</div>
    <?php endif; ?>


    <div class="row">

        <div class="col-md-8">
            <div class="card">
                <div class="card-body pb-1">

                    <div class="row mb-3">
                        <div class="col-12 text-end">
                            <b>No. de Folio:</b> 00<?= $detail['id'] ?>
                            <p><?= $detail['fecha_formateada'] ?></p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <b>Lic. Alejandro Guzmán</b>
                            <br>
                            <p><b>Departamento de Recursos Humanos</b></p>
                            <p>Buenos días, por medio de la presente, les informo sobre los días dobles asignados al personal del Departamento de Dirección de Operaciones, correspondientes a la <b>Quincena No. <?= $detail['quincena'] ?></b>,
                                que abarca del <b><?= $detail['inicio_quincena'] ?></b>
                                al <b><?= $detail['fin_quincena'] ?></b>
                                <br> A continuación, detallo la información para cada uno de los colaboradores:
                            </p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table id="tabla-dia-doble-form" class="table table-striped table-bordered mb-0 align-middle portal-table portal-table--selectable" width="100%">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle" width="50">#</th>
                                            <th class="align-middle">Empleado</th>
                                            <th class="text-center align-middle">Día Doble</th>
                                            <?php if ($detail['status'] == 0 && $puedeEditar): ?>
                                                <th class="text-center align-middle" width="48px"><i class="ti ti-trash text-danger fs-5"></i></th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($detail['empleados']) > 0): ?>
                                            <?php foreach ($detail['empleados'] as $idx => $emp): ?>
                                                <tr>
                                                    <td class="text-center align-middle fw-bold"><?= ($idx + 1) ?></td>
                                                    <td class="align-middle fw-semibold"><?= htmlspecialchars($emp['nombre']) ?></td>
                                                    <td class="text-center align-middle"><?= htmlspecialchars($emp['fecha_label']) ?></td>
                                                    <?php if ($detail['status'] == 0 && $puedeEditar): ?>
                                                        <td class="text-center align-middle">
                                                            <a class="pointer" class="text-danger"
                                                                @click="async () => {
    const res = await deleteAction({
        url: '/departamento-operativo/recursos-humanos/dia-doble/delete-personal',
        id: <?= $emp['id'] ?>,
        name: '<?= addslashes($emp['nombre']) ?>'
    });
    if (res && res.success) setTimeout(() => location.reload(), 2000);
}"
                                                                title="Eliminar">
                                                                <i class="ti ti-trash text-danger fs-5 pointer"></i>
                                                            </a>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?= $detail['status'] == 0 && $puedeEditar ? '4' : '3' ?>" class="text-center text-muted py-2">No se encontro información</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 mt-4 text-center">
                            <p>Sin más por el momento quedo de usted.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <?php if ($detail['status'] == 0 && $puedeEditar): ?>
            <div class="col-md-4 d-flex">
                <div class="card border-0 bg-white w-100 d-flex flex-column">
                    <div class="card-header text-bg-primary py-3 border-0 flex-shrink-0">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">
                                <i class="ti ti-signature fs-6"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="mb-0 text-white">FIRMA DE QUIEN ELABORA</h5>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-3 flex-fill d-flex flex-column">
                        <div id="firma-pad-wrap-form" class="signature-pad-wrapper flex-fill d-flex" style="border:2px dashed #adb5bd;border-radius:6px;cursor:crosshair;">
                            <div class="signature-pad--body w-100 h-100 d-flex">
                                <canvas id="firma-canvas-form" style="width:100%;height:100%;min-height:0;display:block;touch-action:none;"></canvas>
                            </div>
                        </div>
                        <input type="hidden" name="firma_elaboro" id="firma_elaboro" value="">
                    </div>

                    <button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0 flex-shrink-0" style="border-bottom-left-radius:6px;border-bottom-right-radius:6px;" id="btn-limpiar-firma-form">
                        <i class="ti ti-eraser me-1"></i> Limpiar firma
                    </button>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- MODAL Nuevo PERSONAL -->
    <div class="modal fade" id="modalAgregarPersonal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"><i class="ti ti-user-plus"></i> Nuevo Personal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">* Nombre del personal:</label>
                        <div class="select2-modal-field">
                            <select class="form-select" id="personalSelect" style="width:100%;">
                                <option value="">Selecciona una opcion...</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">* Día doble:</label>
                        <input type="date" class="form-control" id="fechaDiaDoble">
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                      <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    </div>
                     <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button" class="btn btn-success" @click="guardarPersonal()" :disabled="guardandoPersonal">
                        <template x-if="!guardandoPersonal"><span><i class="ti ti-check me-1"></i>Guardar</span></template>
                        <template x-if="guardandoPersonal"><span class="d-inline-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span> Guardando...</span></template>
                    </button>
                    </div>
                  </div>

                    

                    
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR QUINCENA -->
    <div class="modal fade" id="modalEditarQuincena" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"><i class="ti ti-calendar me-1"></i> Editar Quincena</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="">
                        <label class="form-label">* No. de quincena:</label>
                        <div class="select2-modal-field">
                            <select class="form-select" id="quincenaEditSelect" style="width:100%;">
                                <option value="">Selecciona una opcion...</option>
                                <?php foreach ($quincenas as $q): ?>
                                    <option value="<?= $q['numero'] ?>" <?= $q['numero'] == $detail['quincena'] ? 'selected' : '' ?>><?= $q['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                        <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                        <i class="ti ti-x"></i> Cancelar
                    </button>

                    </div>
                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                       <button type="button" class="btn btn-success" @click="guardarQuincena()" :disabled="guardandoQuincena">
                        <template x-if="!guardandoQuincena"><span><i class="ti ti-check me-1"></i>Guardar</span></template>
                        <template x-if="guardandoQuincena"><span class="d-inline-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span> Guardando...</span></template>
                    </button>
                    </div>
                  </div>

                </div>
            </div>
        </div>
    </div>

    <!-- MODAL FIRMAR TOKEN (VoBO / Autorizacion) -->
    <div class="modal fade" id="modalFirmaToken" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="firmaTokenTitle">Firmar Formulario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-success btn-sm" @click="crearToken('email')">
                                <i class="ti ti-mail me-1"></i> Token via e-mail
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" @click="crearToken('telegram')">
                                <i class="ti ti-brand-telegram me-1"></i> Token via Telegram
                            </button>
                        </div>
                        <small class="text-secondary">Ingrese el token enviado a su telefono o correo electronico.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">* TOKEN:</label>
                        <input type="text" class="form-control" id="tokenInput" placeholder="Token de seguridad">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" @click="firmarToken()">
                        <i class="ti ti-check me-1"></i>Firmar
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
