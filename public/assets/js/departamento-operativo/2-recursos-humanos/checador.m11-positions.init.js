document.addEventListener('alpine:init', () => {
    Alpine.data('m11PositionCatalog', () => ({
        table: null,
        modal: null,
        mode: 'create',
        id: null,
        name: '',
        rootElement: null,
        loading: false,
        fieldError: '',
        error: '',
        success: '',

        init() {
            const element = this.$el;
            this.rootElement = element;
            let positions = [];
            try {
                positions = JSON.parse(element.dataset.positionItems || '[]');
            } catch (_) {
                this.error = 'No fue posible cargar la lista de puestos.';
                return;
            }

            const canEdit = Boolean(element.querySelector('#m11-position-modal'));
            this.table = $('#m11-position-table').DataTable({
                data: positions,
                processing: false,
                serverSide: false,
                autoWidth: false,
                stateSave: true,
                order: [[0, 'asc']],
                pageLength: 10,
                language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
                columns: [
                    {
                        data: 'name',
                        render: (data, type) => type === 'display' ? this.escapeHtml(data) : String(data ?? ''),
                    },
                    {
                        data: null,
                        width: '1%',
                        orderable: false,
                        searchable: false,
                        className: 'text-center align-middle',
                        render: (_data, _type, row) => {
                            if (!canEdit) return '';
                            const id = Number(row.id);
                            if (!Number.isSafeInteger(id) || id < 1) return '';
                            return `<div class="dropdown dropstart">
                                <a class="pointer" data-bs-toggle="dropdown" aria-label="Opciones del puesto" aria-expanded="false" role="button" tabindex="0">
                                    <i class="ti ti-dots-vertical fs-6"></i>
                                </a>
                                <ul class="dropdown-menu"><li><a class="dropdown-item pointer m11-position-edit" role="button" tabindex="0" data-position-id="${id}">
                                    <i class="ti ti-edit" aria-hidden="true"></i> Editar
                                </a></li></ul>
                            </div>`;
                        },
                    },
                ],
            });

            if (!canEdit) return;
            const modalElement = document.getElementById('m11-position-modal');
            this.modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modalElement.addEventListener('hidden.bs.modal', () => this.resetForm());
            $('#m11-position-table tbody').on('click', '.m11-position-edit', (event) => {
                const button = event.currentTarget;
                const id = Number(button.dataset.positionId);
                const row = this.table.row($(button).closest('tr')).data();
                if (!row || Number(row.id) !== id) {
                    this.error = 'No fue posible identificar el puesto seleccionado. Actualiza la página e inténtalo de nuevo.';
                    return;
                }
                window.dispatchEvent(new CustomEvent('m11-position-edit', {
                    detail: { id, name: String(row.name ?? '') },
                }));
            });
            $('#m11-position-table tbody').on('keydown', '.dropdown > a[data-bs-toggle="dropdown"], .m11-position-edit', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                event.currentTarget.click();
            });
        },

        escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (character) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[character]));
        },

        openCreate() {
            this.mode = 'create';
            this.id = null;
            this.name = '';
            this.fieldError = '';
            this.error = '';
            this.modal?.show();
        },

        openEdit(position) {
            const id = Number(position?.id);
            if (!Number.isSafeInteger(id) || id < 1) {
                this.error = 'El puesto seleccionado no tiene un identificador válido.';
                return;
            }
            this.mode = 'edit';
            this.id = id;
            this.name = String(position.name ?? '');
            this.fieldError = '';
            this.error = '';
            this.modal?.show();
        },

        async save() {
            if (this.loading) return;
            const name = String(this.name ?? '').trim();
            if (!name) {
                this.fieldError = 'El nombre del puesto es obligatorio.';
                document.getElementById('m11-position-name')?.focus();
                return;
            }
            if (name.length > 100) {
                this.fieldError = 'El nombre no puede exceder 100 caracteres.';
                return;
            }
            if (this.mode === 'edit' && (!Number.isSafeInteger(this.id) || this.id < 1)) {
                this.error = 'El identificador del puesto no es válido. Cierra el modal y vuelve a seleccionarlo.';
                return;
            }

            this.loading = true;
            this.fieldError = '';
            this.error = '';
            this.success = '';
            const payload = {
                position_name: name,
                position_id: this.mode === 'edit' ? this.id : null,
                workplace_id: this.rootElement.dataset.workplaceId || null,
            };

            try {
                const response = await axios.post(this.rootElement.dataset.saveUrl, payload, {
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.rootElement.dataset.csrfToken,
                    },
                });
                const body = response.data || {};
                const item = body.item || {};
                if (body.ok !== true || !Number.isSafeInteger(Number(item.id)) || String(item.name ?? '') !== name) {
                    throw { response: { status: response.status, data: body } };
                }

                const confirmed = { id: Number(item.id), name: String(item.name) };
                if (this.mode === 'create') {
                    this.table.row.add(confirmed).draw(false);
                    this.success = 'Puesto creado y confirmado.';
                } else {
                    const rowIndexes = this.table.rows().indexes().toArray();
                    const rowIndex = rowIndexes.find((index) => Number(this.table.row(index).data()?.id) === confirmed.id);
                    if (rowIndex === undefined) {
                        throw new Error('El servidor confirmó el cambio, pero no se encontró la fila para actualizar. Actualiza la página para sincronizar la tabla.');
                    }
                    this.table.row(rowIndex).data(confirmed).draw(false);
                    this.success = 'Puesto actualizado y confirmado.';
                }
                this.modal?.hide();
            } catch (failure) {
                const status = Number(failure?.response?.status || 0);
                const body = failure?.response?.data || {};
                this.error = body.message || (status === 401
                    ? 'Tu sesión expiró. Inicia sesión nuevamente.'
                    : status === 403
                        ? 'No tienes permiso para modificar el catálogo de puestos.'
                        : status === 409
                            ? 'Ya existe un puesto activo con ese nombre.'
                            : status === 419
                                ? 'La protección de sesión venció. Actualiza la página antes de guardar.'
                                : status >= 500
                                    ? 'El servidor no pudo confirmar el cambio. Intenta de nuevo más tarde.'
                                    : (failure?.message || 'No se pudo guardar el puesto. Revisa los datos e inténtalo de nuevo.'));
            } finally {
                this.loading = false;
            }
        },

        resetForm() {
            if (this.loading) return;
            this.mode = 'create';
            this.id = null;
            this.name = '';
            this.fieldError = '';
            this.error = '';
        },
    }));
});
