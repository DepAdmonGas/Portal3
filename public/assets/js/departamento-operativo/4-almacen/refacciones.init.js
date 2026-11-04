document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('refacciones-module');
    if (!root) return;

    const lowStockTable = root.querySelector('#tabla-refacciones-stock-bajo');
    if (lowStockTable && window.jQuery && jQuery.fn.DataTable && !jQuery.fn.DataTable.isDataTable(lowStockTable)) {
        jQuery(lowStockTable).DataTable({
            autoWidth: false,
            stateSave: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[1, 'asc']],
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
            columnDefs: [{ targets: [0, 1, 3, 4, 5], className: 'text-center' }],
        });
    }

    const upcomingEventsTable = root.querySelector('#tabla-refacciones-proximos-eventos');
    if (upcomingEventsTable && window.jQuery && jQuery.fn.DataTable && !jQuery.fn.DataTable.isDataTable(upcomingEventsTable)) {
        jQuery(upcomingEventsTable).DataTable({
            autoWidth: false,
            stateSave: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[1, 'asc']],
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
            columnDefs: [{ targets: [0, 1, 2], className: 'text-center' }],
        });
    }

    const reportSection = root.querySelector('[data-refacciones-reportes]');
    if (reportSection) {
        const reportForm = reportSection.querySelector('[data-report-filter]');
        const periodValue = reportForm?.querySelector('[data-report-period-value]');
        const yearValue = reportForm?.querySelector('[data-report-year-value]');
        const monthValue = reportForm?.querySelector('[data-report-month-value]');
        const yearInputs = [...(reportForm?.querySelectorAll('[data-report-year]') || [])];
        const monthPicker = reportForm?.querySelector('[data-report-month-picker]');
        const semesterInput = reportForm?.querySelector('[data-report-semester]');
        const reportStateKey = `refacciones-reportes:${window.location.pathname}`;
        const syncReportControls = () => {
            if (!reportForm || !periodValue) return;
            reportForm.querySelectorAll('[data-report-period]').forEach((button) => {
                const selected = button.dataset.reportPeriod === periodValue.value;
                button.classList.toggle('btn-primary', selected);
                button.classList.toggle('btn-outline-primary', !selected);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            reportForm.querySelectorAll('[data-report-controls]').forEach((controls) => {
                controls.classList.toggle('d-none', controls.dataset.reportControls !== periodValue.value);
            });
        };
        const persistReportState = () => {
            if (!periodValue || !yearValue || !monthValue) return;
            try {
                sessionStorage.setItem(reportStateKey, JSON.stringify({
                    periodo: periodValue.value,
                    year: yearValue.value,
                    month: monthValue.value,
                    semestre: semesterInput?.value || (Number(monthValue.value) > 6 ? '2' : '1'),
                }));
            } catch (_) { /* El reporte sigue funcionando si el navegador bloquea el almacenamiento de sesión. */ }
        };
        try {
            const savedState = JSON.parse(sessionStorage.getItem(reportStateKey) || 'null');
            if (savedState && ['mensual', 'semestral', 'anual'].includes(savedState.periodo)) {
                const savedYear = Number(savedState.year);
                const savedMonth = Number(savedState.month);
                const currentYear = new Date().getFullYear();
                if (savedYear >= 2024 && savedYear <= currentYear && savedMonth >= 1 && savedMonth <= 12) {
                    periodValue.value = savedState.periodo;
                    yearValue.value = String(savedYear);
                    monthValue.value = String(savedMonth);
                    yearInputs.forEach((input) => { input.value = String(savedYear); });
                    if (monthPicker) monthPicker.value = `${savedYear}-${String(savedMonth).padStart(2, '0')}`;
                    if (semesterInput) semesterInput.value = savedState.semestre === '2' ? '2' : '1';
                }
            }
        } catch (_) { /* Se conserva el período inicial renderizado si no hay estado guardado válido. */ }
        syncReportControls();
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!link) return;
            try {
                const destination = new URL(link.href, window.location.href);
                if (destination.origin === window.location.origin && destination.pathname !== window.location.pathname) {
                    sessionStorage.removeItem(reportStateKey);
                }
            } catch (_) { /* Ignora enlaces que no puedan resolverse como URL. */ }
        });
        let reportRequest = 0;
        const formatReportQuantity = (value) => new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(Number(value || 0));
        const formatReportMoney = (value) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0));
        const renderReport = (data) => {
            reportSection.querySelector('[data-report-period-label]').textContent = `Período: ${data.label || ''}`;
            reportSection.querySelector('[data-report-metric-value="entradas"]').textContent = formatReportQuantity(data.entradas);
            reportSection.querySelector('[data-report-metric-value="valor_entradas"]').textContent = formatReportMoney(data.valor_entradas);
            reportSection.querySelector('[data-report-metric-value="salidas"]').textContent = formatReportQuantity(data.salidas);
            reportSection.querySelector('[data-report-metric-value="valor_salidas"]').textContent = formatReportMoney(data.valor_salidas);
            reportSection.querySelector('[data-report-total="entradas"]').textContent = formatReportQuantity(data.entradas);
            reportSection.querySelector('[data-report-total="valor_entradas"]').textContent = formatReportMoney(data.valor_entradas);
            reportSection.querySelector('[data-report-total="salidas"]').textContent = formatReportQuantity(data.salidas);
            reportSection.querySelector('[data-report-total="valor_salidas"]').textContent = formatReportMoney(data.valor_salidas);

            const entries = Array.isArray(data.detalle) ? data.detalle : [];
            if (reportTableData) {
                reportTableData.clear();
                reportTableData.rows.add(entries.map((row) => [
                    escapeReportText(row.sku),
                    escapeReportText(row.nombre),
                    formatReportQuantity(row.entradas),
                    formatReportMoney(row.valor_entradas),
                    formatReportQuantity(row.salidas),
                    formatReportMoney(row.valor_salidas),
                ]));
                reportTableData.draw(false);
            }
        };
        const escapeReportText = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
        const loadReport = async () => {
            if (!reportForm || !periodValue || !yearValue || !monthValue) return;
            const requestId = ++reportRequest;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            try {
                const response = await fetch(reportForm.dataset.reportEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        periodo: periodValue.value || 'mensual',
                        year: yearValue.value,
                        month: monthValue.value,
                    }),
                });
                const result = await response.json();
                if (!response.ok || !result.success || !result.data) throw new Error(result.message || 'No se pudo actualizar el reporte.');
                if (requestId === reportRequest) renderReport(result.data);
            } catch (error) {
                if (requestId === reportRequest && window.Notify) Notify.error(error.message || 'No se pudo actualizar el reporte.');
            }
        };
        const sendReportButton = reportSection.querySelector('[data-report-send-email]');
        sendReportButton?.addEventListener('click', async () => {
            if (!periodValue || !yearValue || !monthValue || sendReportButton.disabled) return;
            const originalContent = sendReportButton.innerHTML;
            sendReportButton.disabled = true;
            sendReportButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Enviando…';
            window.loader?.show();
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(sendReportButton.dataset.reportEmailEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ periodo: periodValue.value, year: yearValue.value, month: monthValue.value }),
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'No se pudo enviar el reporte.');
                if (window.Notify) Notify.success(result.message || 'Reporte enviado por correo.');
            } catch (error) {
                if (window.Notify) Notify.error(error.message || 'No se pudo enviar el reporte.');
            } finally {
                window.loader?.hide();
                sendReportButton.disabled = false;
                sendReportButton.innerHTML = originalContent;
            }
        });
        reportForm?.addEventListener('submit', (event) => event.preventDefault());

        reportForm?.querySelectorAll('[data-report-period]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!periodValue) return;
                periodValue.value = button.dataset.reportPeriod || 'mensual';
                syncReportControls();
                if (button.dataset.reportPeriod === 'mensual' && monthPicker && yearValue && monthValue) {
                    monthPicker.value = `${yearValue.value}-${String(Number(monthValue.value)).padStart(2, '0')}`;
                }
                if (button.dataset.reportPeriod === 'semestral' && semesterInput && monthValue) {
                    semesterInput.value = Number(monthValue.value) > 6 ? '2' : '1';
                }
                if (yearValue) yearInputs.forEach((input) => { input.value = yearValue.value; });
                persistReportState();
                loadReport();
            });
        });
        monthPicker?.addEventListener('change', () => {
            const match = /^(\d{4})-(\d{2})$/.exec(monthPicker.value);
            if (!match) return;
            if (yearValue) yearValue.value = match[1];
            if (monthValue) monthValue.value = String(Number(match[2]));
            persistReportState();
            loadReport();
        });
        yearInputs.forEach((yearInput) => yearInput.addEventListener('change', () => {
            if (yearValue) yearValue.value = yearInput.value;
            yearInputs.forEach((input) => { input.value = yearInput.value; });
            persistReportState();
            loadReport();
        }));
        semesterInput?.addEventListener('change', () => {
            if (monthValue) monthValue.value = semesterInput.value === '2' ? '7' : '1';
            persistReportState();
            loadReport();
        });

        const reportTable = reportSection.querySelector('[data-refacciones-report-table]');
        let reportTableData = null;
        if (reportTable && window.jQuery && jQuery.fn.DataTable) {
            reportTableData = jQuery.fn.DataTable.isDataTable(reportTable)
                ? jQuery(reportTable).DataTable()
                : jQuery(reportTable).DataTable({
                    autoWidth: false,
                    stateSave: false,
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    order: [[1, 'asc']],
                    language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
                    columnDefs: [{ targets: [0, 2, 3, 4, 5], className: 'text-center' }],
                });
        }

        // Pide el período actual al entrar al módulo, después de inicializar
        // DataTables para que la respuesta también actualice la tabla visible.
        loadReport();
    }

    const inventoryTables = [...root.querySelectorAll('[data-refacciones-inventory-table]')];
    if (inventoryTables.length && window.jQuery && jQuery.fn.DataTable) {
        inventoryTables.forEach((table) => {
            if (jQuery.fn.DataTable.isDataTable(table)) return;
            jQuery(table).DataTable({
                autoWidth: false,
                stateSave: false,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[1, 'asc']],
                language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
                columnDefs: [
                    { targets: [0, 2, 3, 4], className: 'text-center' },
                ],
            });
        });
    }

    const productsSection = document.getElementById('refacciones-productos') || document.getElementById('refacciones-calcomanias');
    if (productsSection) {
        const isCalcomania = productsSection.id === 'refacciones-calcomanias';
        const tableId = isCalcomania ? 'tabla-refacciones-calcomanias' : 'tabla-refacciones-productos';
        let products = [];
        try { products = JSON.parse(productsSection.dataset.products || '[]'); } catch (_) { products = []; }
        const productsById = new Map(products.map((product) => [String(product.id), product]));
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
        const imageUrl = (product) => product.imagen ? `/uploads/archivos/refacciones/${encodeURIComponent(String(product.imagen).split(/[\\/]/).pop())}` : '';
        const money = (value) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0));
        const decimal2 = (value) => {
            const parsed = Number(value || 0);
            return Number.isFinite(parsed) ? Number(parsed.toFixed(2)) : 0;
        };
        const quantity = (value) => {
            const numericValue = Number(value || 0);
            if (!Number.isFinite(numericValue) || numericValue === 0) return '0';
            return numericValue.toFixed(2).replace(/\.?0+$/, '');
        };
        let currentProductId = null;
        let cardsPage = 1;
        const cardsPerPage = 9;
        let cards = [...productsSection.querySelectorAll('[data-product-card-item]')];
        const cardsContainer = productsSection.querySelector('[data-product-cards]');
        const emptyCatalog = productsSection.querySelector('[data-product-empty]');
        const cardsPrev = productsSection.querySelector('[data-cards-prev]');
        const cardsNext = productsSection.querySelector('[data-cards-next]');
        const cardsPageLabel = productsSection.querySelector('[data-cards-page]');

        const renderCardsPage = () => {
            const pageCount = Math.max(1, Math.ceil(cards.length / cardsPerPage));
            cardsPage = Math.min(cardsPage, pageCount);
            cards.forEach((card, index) => { card.classList.toggle('d-none', index < (cardsPage - 1) * cardsPerPage || index >= cardsPage * cardsPerPage); });
            if (cardsPageLabel) cardsPageLabel.textContent = `${cardsPage} / ${pageCount}`;
            if (cardsPrev) cardsPrev.disabled = cardsPage <= 1;
            if (cardsNext) cardsNext.disabled = cardsPage >= pageCount;
            const count = productsSection.querySelector('[data-cards-count]');
            if (count) {
                const noun = isCalcomania ? 'calcomanía' : 'producto';
                count.textContent = `${cards.length} ${noun}${cards.length === 1 ? '' : 's'}`;
            }
        };
        renderCardsPage();
        emptyCatalog?.classList.toggle('d-none', products.length > 0);
        cardsPrev?.addEventListener('click', () => { cardsPage--; renderCardsPage(); });
        cardsNext?.addEventListener('click', () => { cardsPage++; renderCardsPage(); });

        let dataTable = null;
        const initializeTable = () => {
            const table = document.getElementById(tableId);
            if (!table || !window.jQuery || !jQuery.fn.DataTable || dataTable) return;
            dataTable = jQuery(table).DataTable({
                autoWidth: false,
                stateSave: false,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[2, 'asc']],
                language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
                columnDefs: isCalcomania
                    ? [
                        { targets: [0, 7], orderable: false, searchable: false },
                        { targets: [0, 1, 3, 4, 5, 6, 7], className: 'text-center' },
                        { targets: 2, className: 'text-start' },
                        { targets: 7, className: 'text-center text-nowrap' },
                    ]
                    : [
                        { targets: [0, 10], orderable: false, searchable: false },
                        { targets: [0, 1, 3, 4, 5, 6, 7, 8, 10], className: 'text-center' },
                        { targets: 2, className: 'text-start' },
                        { targets: 9, className: 'text-end' },
                        { targets: 10, className: 'text-center text-nowrap' },
                    ],
            });
            dataTable.on('draw', () => {
                // Rebuild Bootstrap dropdown triggers after DataTables replaces a page of rows.
                if (window.bootstrap?.Dropdown) table.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((button) => window.bootstrap.Dropdown.getOrCreateInstance(button));
            });
        };

        const productCardTemplate = productsSection.querySelector('[data-product-card-template]');
        const productCardItem = (product) => {
            const item = productCardTemplate?.content.firstElementChild?.cloneNode(true);
            if (!item) return document.createElement('div');
            const specification = [product.nombre, product.medida, product.modelo, product.marca].filter(Boolean).join(' ');
            const article = item.querySelector('[data-product-detail]');
            article.dataset.productDetail = String(product.id);
            article.setAttribute('aria-label', `Ver detalle de ${product.nombre || product.sku}`);
            item.querySelector('[data-product-card-sku]').textContent = String(product.sku || '');
            if (isCalcomania) {
                item.querySelector('[data-calcomania-value="nombre"]').textContent = String(product.nombre || '—');
                item.querySelector('[data-calcomania-value="dimension"]').textContent = product.dimension || '—';
                item.querySelector('[data-calcomania-value="colores"]').textContent = product.colores_pantone || '—';
                item.querySelector('[data-calcomania-value="acabado"]').textContent = product.acabado || '—';
                item.querySelector('[data-calcomania-value="reproduccion"]').textContent = product.reproduccion || '—';
            } else {
                item.querySelector('[data-product-card-value="nombre"]').textContent = String(product.nombre || '—');
                item.querySelector('[data-product-card-value="tamano"]').textContent = [product.medida, product.modelo].filter(Boolean).join(' ') || '—';
                item.querySelector('[data-product-card-value="especificacion"]').textContent = specification || '—';
                item.querySelector('[data-product-card-value="marca"]').textContent = product.marca || '—';
                item.querySelector('[data-product-card-value="ubicacion"]').textContent = productsSection.dataset.location || 'Almacén central';
            }
            const image = item.querySelector('[data-product-card-image]');
            const emptyImage = item.querySelector('[data-product-card-image-empty]');
            if (product.imagen) {
                image.src = imageUrl(product);
                image.alt = `Imagen de ${product.nombre || ''}`;
                image.classList.remove('d-none');
                emptyImage.classList.add('d-none');
            }
            return item;
        };

        const cardSearch = productsSection.querySelector('[data-product-card-search]');
        const emptyCatalogMessage = productsSection.querySelector('[data-product-empty-message]');
        const normalizeSearch = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es-MX').trim();
        const renderCards = () => {
            const query = normalizeSearch(cardSearch?.value);
            const filteredProducts = products.filter((product) => {
                const searchableText = isCalcomania
                    ? [product.sku, product.nombre, product.dimension, product.colores_pantone, product.acabado, product.reproduccion, product.notas_calcomania].join(' ')
                    : [product.sku, product.nombre, product.medida, product.modelo, product.marca, product.categoria, product.unidad_medida].join(' ');
                return normalizeSearch(searchableText).includes(query);
            });
            cardsPage = 1;
            if (cardsContainer) cardsContainer.replaceChildren(...filteredProducts.map(productCardItem));
            cards = [...productsSection.querySelectorAll('[data-product-card-item]')];
            renderCardsPage();
            emptyCatalog?.classList.toggle('d-none', filteredProducts.length > 0);
            if (emptyCatalogMessage) {
                emptyCatalogMessage.textContent = products.length === 0
                    ? (isCalcomania ? 'Aún no hay calcomanías registradas.' : 'Aún no hay artículos en este catálogo.')
                    : 'No se encontraron fichas con ese criterio de búsqueda.';
            }
            const total = productsSection.querySelector('[data-product-total]');
            if (total) total.textContent = `(${products.length})`;
        };
        cardSearch?.addEventListener('input', renderCards);
        renderCards();

        const productTableRow = (product) => {
            const row = document.createElement('tr');
            row.dataset.productRowId = String(product.id);
            const image = product.imagen
                ? `<img class="refacciones-product-thumb" src="${escapeHtml(imageUrl(product))}" alt="" loading="lazy">`
                : '<span class="refacciones-product-thumb-placeholder"></span>';
            const editAction = productsSection.dataset.canEdit === '1' ? `<a href="javascript:void(0)" class="dropdown-item pointer" data-product-edit="${escapeHtml(product.id)}"><i class="ti ti-pencil me-1"></i>Editar</a>` : '';
            const deleteAction = productsSection.dataset.canDelete === '1' ? `<a href="javascript:void(0)" class="dropdown-item pointer text-danger" data-product-delete="${escapeHtml(product.id)}"><i class="ti ti-trash me-1"></i>Eliminar</a>` : '';
            const priceHistoryAction = `<a href="javascript:void(0)" class="dropdown-item pointer" data-product-price-history="${escapeHtml(product.id)}"><i class="ti ti-history me-1"></i>Precios del producto</a>`;
            if (isCalcomania) {
                row.innerHTML = `<td>${image}</td><td>${escapeHtml(product.sku)}</td><td class="text-start">${escapeHtml(product.nombre)}</td><td>${escapeHtml(product.dimension || '—')}</td><td>${escapeHtml(product.colores_pantone || '—')}</td><td>${escapeHtml(product.acabado || '—')}</td><td>${escapeHtml(product.reproduccion || '—')}</td><td class="text-nowrap"><div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown" title="Opciones" aria-label="Acciones de ${escapeHtml(product.sku)}"><i class="ti ti-dots-vertical fs-6"></i></a><div class="dropdown-menu"><a href="javascript:void(0)" class="dropdown-item pointer" data-product-detail="${escapeHtml(product.id)}"><i class="ti ti-eye me-1"></i>Detalle</a>${editAction}${deleteAction}</div></div></td>`;
                return row;
            }
            const stock = Number(product.existencia || 0);
            const minimum = Number(product.stock_minimo || 0);
            row.innerHTML = `<td>${image}</td><td>${escapeHtml(product.sku)}</td><td>${escapeHtml(product.nombre)}</td><td>${escapeHtml(product.medida || '—')}</td><td>${escapeHtml(product.modelo || '—')}</td><td>${escapeHtml(product.marca || '—')}</td><td>${escapeHtml(product.categoria || '—')}</td><td class="text-end ${stock <= minimum ? 'text-warning fw-semibold' : ''}">${quantity(stock)} ${escapeHtml(product.unidad_medida || '')}</td><td class="text-end">${quantity(minimum)}</td><td class="text-end" data-order="${escapeHtml(product.costo_unitario)}">${money(product.costo_unitario)}</td><td class="text-center text-nowrap"><div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown" title="Opciones" aria-label="Acciones de ${escapeHtml(product.sku)}"><i class="ti ti-dots-vertical fs-6"></i></a><div class="dropdown-menu"><a href="javascript:void(0)" class="dropdown-item pointer" data-product-detail="${escapeHtml(product.id)}"><i class="ti ti-eye me-1"></i>Detalle</a>${priceHistoryAction}${editAction}${deleteAction}</div></div></td>`;
            return row;
        };

        const renderProductCollection = () => {
            products = [...productsById.values()].sort((a, b) => String(a.nombre).localeCompare(String(b.nombre), 'es'));
            renderCards();

            const table = document.getElementById(tableId);
            const rows = products.map(productTableRow);
            if (dataTable) dataTable.clear().rows.add(rows).draw(false);
            else table?.querySelector('tbody')?.replaceChildren(...rows);
        };

        productsSection.addEventListener('refacciones:producto-guardado', (event) => {
            const { formData, response, id } = event.detail || {};
            if (!(formData instanceof FormData) || !response?.success) return;
            const productId = String(response.id || id || '');
            const existing = productsById.get(productId);
            if (!productId) return;
            const product = isCalcomania ? {
                ...(existing || {}), id: productId, tipo_articulo: 'calcomania',
                sku: formData.get('sku'), nombre: formData.get('nombre'),
                dimension: formData.get('dimension'), colores_pantone: formData.get('colores_pantone'),
                acabado: formData.get('acabado'), reproduccion: formData.get('reproduccion'),
                notas_calcomania: formData.get('notas'), descripcion: formData.get('notas'),
                medida: formData.get('dimension'), categoria: 'Calcomanías', existencia: 0,
                unidad_medida: 'Pieza', imagen: response.imagen || existing?.imagen || '',
            } : {
                ...(existing || {}),
                id: productId,
                sku: formData.get('sku'),
                nombre: formData.get('nombre'),
                medida: formData.get('medida'),
                modelo: formData.get('modelo'),
                marca: formData.get('marca'),
                categoria: formData.get('categoria'),
                unidad_medida: formData.get('unidad_medida'),
                stock_minimo: decimal2(formData.get('stock_minimo')),
                costo_unitario: decimal2(formData.get('costo_unitario')),
                existencia: existing?.existencia ?? 0,
                imagen: response.imagen || existing?.imagen || '',
            };
            productsById.set(productId, product);
            renderProductCollection();
        });

        const productViewStorageKey = `portal3:refacciones:${isCalcomania ? 'calcomanias' : 'productos'}:view`;
        const cardSearchControl = productsSection.querySelector('[data-product-card-search-control]');
        const setProductView = (view, persist = false) => {
            const selectedView = view === 'table' ? 'table' : 'cards';
            productsSection.querySelectorAll('[data-product-view]').forEach((button) => {
                const selected = button.dataset.productView === selectedView;
                button.classList.toggle('btn-primary', selected);
                button.classList.toggle('text-white', selected);
                button.classList.toggle('bg-primary-subtle', !selected);
                button.classList.toggle('text-primary', !selected);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            productsSection.querySelectorAll('[data-product-panel]').forEach((panel) => panel.classList.toggle('d-none', panel.dataset.productPanel !== selectedView));
            cardSearchControl?.classList.toggle('d-none', selectedView !== 'cards');
            if (persist) {
                try { window.localStorage.setItem(productViewStorageKey, selectedView); } catch (_) { /* Storage may be unavailable in private browsing. */ }
            }
            if (selectedView === 'table') {
                initializeTable();
                window.setTimeout(() => dataTable?.columns.adjust().draw(false), 0);
            }
        };
        let savedProductView = 'cards';
        try { savedProductView = window.localStorage.getItem(productViewStorageKey) === 'table' ? 'table' : 'cards'; } catch (_) { /* Use the default cards view. */ }
        setProductView(savedProductView);

        const detailModalElement = document.getElementById(isCalcomania ? 'modalRefaccionesCalcomaniaDetalle' : 'modalRefaccionesProductoDetalle');
        const detailModal = detailModalElement && window.bootstrap?.Modal ? window.bootstrap.Modal.getOrCreateInstance(detailModalElement) : null;
        const productModalElement = document.getElementById(isCalcomania ? 'modalRefaccionesCalcomania' : 'modalRefaccionesProducto');
        const productModal = productModalElement && window.bootstrap?.Modal ? window.bootstrap.Modal.getOrCreateInstance(productModalElement) : null;
        const showDetail = (productId) => {
            const product = productsById.get(String(productId));
            if (!product || !detailModalElement || !detailModal) return;
            currentProductId = String(product.id);
            const set = (selector, value) => {
                const element = detailModalElement.querySelector(selector);
                if (!element) return;
                const text = String(value ?? '—') || '—';
                if ('value' in element) element.value = text;
                else element.textContent = text;
            };
            set('[data-detail-sku]', product.sku);
            set('[data-detail-name]', product.nombre);
            if (isCalcomania) {
                set('[data-detail-dimension]', product.dimension);
                set('[data-detail-pantone]', product.colores_pantone);
                set('[data-detail-finish]', product.acabado);
                set('[data-detail-reproduction]', product.reproduccion);
                set('[data-detail-notes]', String(product.notas_calcomania || '').trim() || 'Sin información');
            } else {
                set('[data-detail-measure]', product.medida);
                set('[data-detail-model]', product.modelo);
                set('[data-detail-brand]', product.marca);
                set('[data-detail-category]', product.categoria);
                set('[data-detail-unit]', product.unidad_medida);
                set('[data-detail-stock]', `${quantity(product.existencia)} ${product.unidad_medida ?? ''}`.trim());
                set('[data-detail-minimum]', `${quantity(product.stock_minimo)} ${product.unidad_medida ?? ''}`.trim());
                set('[data-detail-price]', money(product.costo_unitario));
                set('[data-detail-location]', productsSection.dataset.location || 'Almacén central');
                set('[data-detail-full-name]', [product.nombre, product.medida, product.modelo, product.marca].filter(Boolean).join(' ') || '—');
            }
            const image = detailModalElement.querySelector('[data-detail-image]');
            const emptyImage = detailModalElement.querySelector('[data-detail-image-empty]');
            if (image && product.imagen) { image.src = imageUrl(product); image.alt = `Imagen de ${product.nombre || product.sku}`; image.classList.remove('d-none'); emptyImage?.classList.add('d-none'); }
            else { image?.classList.add('d-none'); emptyImage?.classList.remove('d-none'); }
            detailModal.show();
        };

        const form = productModalElement?.querySelector('[data-refacciones-form]');
        const editProduct = (productId) => {
            const product = productsById.get(String(productId));
            if (!product || !form || !productModal) return;
            currentProductId = String(product.id);
            form.dataset.editing = '1';
            form.querySelector('[data-product-id]').value = product.id;
            const editableFields = isCalcomania
                ? ['sku', 'nombre', 'dimension', 'colores_pantone', 'acabado', 'reproduccion', 'notas']
                : ['sku', 'nombre', 'medida', 'modelo', 'marca', 'categoria', 'unidad_medida', 'stock_minimo', 'costo_unitario'];
            editableFields.forEach((field) => {
                const input = form.elements.namedItem(field);
                if (input) {
                    const value = (field === 'notas' ? product.notas_calcomania : product[field]) ?? '';
                    input.value = !isCalcomania && ['stock_minimo', 'costo_unitario'].includes(field)
                        ? quantity(value)
                        : value;
                }
            });
            const fullName = form.querySelector('[data-name-complete]');
            if (fullName) fullName.value = [product.nombre, product.medida, product.modelo, product.marca].filter(Boolean).join(' ') || '—';
            const fileInput = form.elements.namedItem('imagen');
            if (fileInput) {
                fileInput.value = '';
                fileInput.toggleAttribute('data-required', !product.imagen);
            }
            form.querySelector('[data-current-image-help]')?.classList.toggle('d-none', !product.imagen);
            const formTitle = form.querySelector('[data-product-form-title]');
            if (formTitle) formTitle.innerHTML = isCalcomania ? '<i class="ti ti-sticker me-1"></i>Editar calcomanía' : '<i class="ti ti-package me-1"></i>Editar producto';
            const submitLabel = form.querySelector('[data-product-submit-label]');
            if (submitLabel) submitLabel.textContent = 'Actualizar';
            form.querySelectorAll('.is-invalid').forEach((control) => control.classList.remove('is-invalid'));
            if (detailModal && detailModalElement?.classList.contains('show')) {
                detailModalElement.addEventListener('hidden.bs.modal', () => productModal.show(), { once: true });
                detailModal.hide();
            } else {
                productModal.show();
            }
        };
        const resetProductForm = () => {
            if (!form) return;
            form.reset();
            delete form.dataset.editing;
            form.querySelector('[data-product-id]').value = '';
            const formTitle = form.querySelector('[data-product-form-title]');
            if (formTitle) formTitle.innerHTML = isCalcomania ? '<i class="ti ti-sticker me-1"></i>Nueva calcomanía' : '<i class="ti ti-package me-1"></i>Nuevo producto';
            const submitLabel = form.querySelector('[data-product-submit-label]');
            if (submitLabel) submitLabel.textContent = 'Guardar';
            form.querySelector('[data-current-image-help]')?.classList.add('d-none');
            const fileInput = form.elements.namedItem('imagen');
            if (fileInput) fileInput.setAttribute('data-required', '');
            form.querySelectorAll('.is-invalid').forEach((control) => control.classList.remove('is-invalid'));
            const fullName = form.querySelector('[data-name-complete]');
            if (fullName) fullName.value = '—';
        };
        productModalElement?.addEventListener('show.bs.modal', (event) => { if (event.relatedTarget && !form?.dataset.editing) resetProductForm(); });
        productModalElement?.addEventListener('hidden.bs.modal', () => { if (form?.dataset.editing) resetProductForm(); });

        const deleteProduct = async (productId) => {
            const product = productsById.get(String(productId));
            if (!product) return;
            const actions = window.Alpine?.$data(productsSection);
            if (!actions?.deleteAction) return window.Notify?.error('No se pudo iniciar la eliminación del producto.');
            const response = await actions.deleteAction({
                url: `${productsSection.dataset.baseUrl}/productos/eliminar`,
                id: product.id,
                name: `${product.sku} · ${product.nombre}`,
                table: null,
            });
            if (!response?.success) return;
            productsById.delete(String(product.id));
            if (currentProductId === String(product.id)) detailModal?.hide();
            renderProductCollection();
        };

        const priceHistoryModalElement = document.getElementById('modalRefaccionesHistorialPrecios');
        const priceHistoryModal = priceHistoryModalElement && window.bootstrap?.Modal
            ? window.bootstrap.Modal.getOrCreateInstance(priceHistoryModalElement)
            : null;
        const showPriceHistory = async (productId) => {
            if (isCalcomania || !priceHistoryModal || !priceHistoryModalElement) return;
            const product = productsById.get(String(productId));
            if (!product) return;
            const rowsElement = priceHistoryModalElement.querySelector('[data-price-history-rows]');
            const productElement = priceHistoryModalElement.querySelector('[data-price-history-product]');
            const skuElement = priceHistoryModalElement.querySelector('[data-price-history-sku]');
            if (productElement) productElement.textContent = product.nombre || '';
            if (skuElement) skuElement.textContent = product.sku || '';
            if (rowsElement) rowsElement.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Cargando historial…</td></tr>';
            priceHistoryModal.show();
            try {
                const endpoint = `${priceHistoryModalElement.dataset.priceHistoryEndpoint}/${encodeURIComponent(String(productId))}`;
                const response = await fetch(endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const payload = await response.json();
                if (!response.ok || !payload?.success || !Array.isArray(payload.historial)) throw new Error(payload?.message || 'No se pudo consultar el historial de precios.');
                if (productElement) productElement.textContent = payload.producto?.nombre || product.nombre || '';
                if (skuElement) skuElement.textContent = payload.producto?.sku || product.sku || '';
                if (!rowsElement) return;
                rowsElement.replaceChildren();
                if (payload.historial.length === 0) {
                    rowsElement.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Este producto aún no tiene cambios de precio.</td></tr>';
                    return;
                }
                const dateFormatter = new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'long', year: 'numeric', hour: 'numeric', minute: '2-digit' });
                payload.historial.forEach((record) => {
                    const row = document.createElement('tr');
                    const dateCell = document.createElement('td');
                    const oldPriceCell = document.createElement('td');
                    const newPriceCell = document.createElement('td');
                    dateCell.className = 'text-center';
                    oldPriceCell.className = 'text-center';
                    newPriceCell.className = 'text-center fw-semibold';
                    const stamp = record.registrado_en ? new Date(String(record.registrado_en).replace(' ', 'T')) : null;
                    dateCell.textContent = stamp && !Number.isNaN(stamp.getTime()) ? dateFormatter.format(stamp) : '—';
                    oldPriceCell.textContent = record.costo_anterior === null ? 'Precio inicial' : money(record.costo_anterior);
                    newPriceCell.textContent = money(record.costo_nuevo);
                    row.append(dateCell, oldPriceCell, newPriceCell);
                    rowsElement.append(row);
                });
            } catch (error) {
                if (rowsElement) rowsElement.innerHTML = '<tr><td colspan="3" class="text-center text-danger">No se pudo cargar el historial de precios.</td></tr>';
                window.Notify?.error(error.message || 'No se pudo consultar el historial de precios.');
            }
        };

        productsSection.addEventListener('click', (event) => {
            const viewButton = event.target.closest('[data-product-view]');
            if (viewButton) {
                setProductView(viewButton.dataset.productView, true);
                return;
            }
            const card = event.target.closest('[data-product-detail]');
            if (card && card.matches('.refacciones-product-card')) { showDetail(card.dataset.productDetail); return; }
            const detail = event.target.closest('[data-product-detail]');
            if (detail) { event.preventDefault(); showDetail(detail.dataset.productDetail); return; }
            const edit = event.target.closest('[data-product-edit]');
            if (edit) { editProduct(edit.dataset.productEdit); return; }
            const remove = event.target.closest('[data-product-delete]');
            if (remove) { deleteProduct(remove.dataset.productDelete); }
            const priceHistory = event.target.closest('[data-product-price-history]');
            if (priceHistory) { event.preventDefault(); showPriceHistory(priceHistory.dataset.productPriceHistory); }
        });
        productsSection.addEventListener('keydown', (event) => {
            const card = event.target.closest('.refacciones-product-card[data-product-detail]');
            if (card && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); showDetail(card.dataset.productDetail); }
        });
        detailModalElement?.querySelector('[data-detail-edit]')?.addEventListener('click', () => editProduct(currentProductId));
        detailModalElement?.querySelector('[data-detail-delete]')?.addEventListener('click', () => deleteProduct(currentProductId));
        document.getElementById(tableId)?.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-bs-toggle="dropdown"]');
            if (trigger && window.bootstrap?.Dropdown) window.bootstrap.Dropdown.getOrCreateInstance(trigger);
        });
    }

    const chartConsumo = document.getElementById('refacciones-chart-consumo');
    if (chartConsumo && typeof ApexCharts !== 'undefined') {
        const labels = JSON.parse(chartConsumo.dataset.labels || '[]');
        const values = JSON.parse(chartConsumo.dataset.series || '[]');
        new ApexCharts(chartConsumo, {
            chart: { type: 'bar', height: 270, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Salidas', data: values }],
            colors: ['#1f5fdb'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '48%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: labels, labels: { rotate: -25, trim: true } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (value) => Math.round(value) } },
            grid: { borderColor: '#e5e7eb', strokeDashArray: 3 },
            tooltip: { y: { formatter: (value) => `${value} piezas` } },
            noData: { text: 'No hay datos disponibles' },
        }).render();
    }

    const chartCategorias = document.getElementById('refacciones-chart-categorias');
    if (chartCategorias && typeof ApexCharts !== 'undefined') {
        const labels = JSON.parse(chartCategorias.dataset.labels || '[]');
        const values = JSON.parse(chartCategorias.dataset.series || '[]');
        new ApexCharts(chartCategorias, {
            chart: { type: 'pie', height: 270, fontFamily: 'inherit' },
            series: values,
            labels,
            colors: ['#2154c5', '#45a9d1', '#3bb6c5', '#8b4bd9', '#f2a93b', '#48a868'],
            dataLabels: { enabled: true, formatter: (value) => `${Math.round(value)}%` },
            legend: { position: 'bottom', fontSize: '12px' },
            stroke: { colors: ['#fff'], width: 2 },
            tooltip: { y: { formatter: (value) => `${value} piezas` } },
            noData: { text: 'No hay datos disponibles' },
        }).render();
    }

    const modalMovement = document.getElementById('modalRefaccionesMovimiento');
    modalMovement?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const type = button?.dataset.tipo || 'entrada';
        const form = modalMovement.querySelector('form');
        const typeInput = form.querySelector('[data-movement-type]');
        typeInput.value = type;
        modalMovement.querySelector('[data-movement-title]').textContent = type === 'entrada' ? 'Registrar entrada' : 'Registrar salida';
        modalMovement.querySelector('[data-movement-origin]').classList.toggle('d-none', type === 'entrada');
        modalMovement.querySelector('[data-movement-destination]').classList.toggle('d-none', type !== 'entrada');
        modalMovement.querySelectorAll('[data-receipt-fields]').forEach((element) => element.classList.toggle('d-none', type !== 'salida'));
        form.querySelector('[name="id_estacion_origen"]').toggleAttribute('data-required', type !== 'entrada');
        form.querySelector('[name="id_estacion_destino"]').toggleAttribute('data-required', type === 'entrada');
        form.querySelector('[name="nombre_entrega"]').toggleAttribute('data-required', type === 'salida');
        form.querySelector('[name="nombre_recibe"]').toggleAttribute('data-required', type === 'salida');
    });

    document.querySelectorAll('[data-product-type]').forEach((select) => {
        const form = select.closest('form');
        const updateTypeFields = () => {
            form.querySelectorAll('[data-extra-type]').forEach((field) => {
                const visible = field.dataset.extraType === select.value;
                field.classList.toggle('d-none', !visible);
                field.querySelectorAll('input, select, textarea').forEach((control) => {
                    control.toggleAttribute('data-required', visible);
                });
            });
        };
        select.addEventListener('change', updateTypeFields);
        updateTypeFields();
    });

    document.querySelectorAll('[data-refacciones-form]').forEach((form) => {
        const nombreCompleto = form.querySelector('[data-name-complete]');
        const updateFullName = () => {
            if (!nombreCompleto) return;
            nombreCompleto.value = [...form.querySelectorAll('[data-name-part]')]
                .map((input) => input.value.trim())
                .filter(Boolean)
                .join(' ') || '—';
        };
        form.querySelectorAll('[data-name-part]').forEach((input) => input.addEventListener('input', updateFullName));
        updateFullName();
    });

    document.querySelectorAll('[data-refacciones-form]').forEach((form) => {
        form.addEventListener('input', (event) => {
            if (event.target.matches('[data-required]')) event.target.classList.remove('is-invalid');
        });
        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-required]')) event.target.classList.remove('is-invalid');
        });
        form.querySelectorAll('input[type="number"][step="0.01"]').forEach((input) => {
            input.addEventListener('blur', () => {
                if (input.value !== '' && Number.isFinite(Number(input.value))) {
                    input.value = Number(Number(input.value).toFixed(2)).toString();
                }
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            form.querySelectorAll('input[type="number"][step="0.01"]').forEach((input) => {
                if (input.value !== '' && Number.isFinite(Number(input.value))) {
                    input.value = Number(Number(input.value).toFixed(2)).toString();
                }
            });
            const button = form.querySelector('[type="submit"]');
            const invalidFields = [...form.querySelectorAll('[data-required]')].filter((control) => {
                if (control.disabled || control.closest('.d-none')) return false;
                const value = control.type === 'file' ? control.files?.length : String(control.value ?? '').trim();
                const number = control.type === 'number' && value !== '' ? Number(value) : null;
                const invalidNumber = number !== null && (!Number.isFinite(number) || number < Number(control.min || 0));
                const invalidStep = number !== null && control.step && control.step !== 'any'
                    && Math.abs(number / Number(control.step) - Math.round(number / Number(control.step))) > 1e-8;
                const invalidEmail = control.type === 'email' && value !== '' && !control.validity.valid;
                const invalid = !value || invalidNumber || invalidStep || invalidEmail;
                control.classList.toggle('is-invalid', invalid);
                return invalid;
            });
            if (invalidFields.length) {
                if (window.Notify) Notify.error('Completa los campos obligatorios y revisa los valores señalados.');
                invalidFields[0].focus();
                return;
            }
            button.disabled = true;
            try {
                const endpoint = form.dataset.editing ? form.dataset.updateEndpoint : form.dataset.endpoint;
                const formData = new FormData(form);
                const editing = Boolean(form.dataset.editing);
                const id = formData.get('id');
                const response = await axios.post(endpoint, formData);
                if (!response.data?.success) throw new Error(response.data?.message || 'No se pudo guardar la información.');
                // El modal vive fuera de la sección del catálogo; recuperar la sección
                // por ID permite actualizar fichas/tabla sin recargar la página.
                const productSection = document.getElementById(form.dataset.catalogSection || 'refacciones-productos');
                if (productSection) {
                    productSection.dispatchEvent(new CustomEvent('refacciones:producto-guardado', { detail: { formData, response: response.data, editing, id } }));
                    if (window.Notify) Notify.success(response.data.message || (editing ? 'Producto actualizado.' : 'Producto agregado.'));
                    const modalElement = form.closest('.modal');
                    if (modalElement && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                } else {
                    window.location.reload();
                }
            } catch (error) {
                const message = error.response?.data?.message || error.message || 'No se pudo guardar la información.';
                if (window.Notify) Notify.error(message);
            } finally {
                button.disabled = false;
            }
        });
    });

    const entriesSection = document.getElementById('refacciones-entradas');
    if (entriesSection) {
        const entryModal = document.getElementById('modalRefaccionesEntrada');
        const entryForm = entryModal?.querySelector('[data-entry-form]');
        const destinationsSelect = entryForm?.querySelector('[data-entry-destinations]');
        const destinationsWrap = entryForm?.querySelector('[data-entry-destinations-wrap]');
        const singleEditDestination = entryForm?.querySelector('[data-entry-single-edit-destination]');
        const singleEditDestinationName = entryForm?.querySelector('[data-entry-single-edit-destination-name]');
        const quantitiesContainer = entryForm?.querySelector('[data-entry-quantities]');
        const quantitiesSection = entryForm?.querySelector('[data-entry-quantities-section]');
        const productSelect = entryForm?.querySelector('[data-entry-product]');
        const productId = entryForm?.querySelector('[data-entry-product-id]');
        const fixedQuantity = entryForm?.querySelector('[data-entry-fixed-quantity], [data-entry-edit-quantity]');
        const fixedDestination = entryForm?.querySelector('[data-entry-fixed-destination]');
        const quantityTotal = entryForm?.querySelector('[data-entry-total]');
        const table = document.getElementById('tabla-refacciones-entradas');
        let entriesTable = null;
        const escapeEntry = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
        const formatEntryQuantity = (value) => {
            const number = Number(value || 0);
            return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(number);
        };
        const entryDateLabel = (value) => {
            const date = new Date(`${String(value || '').slice(0, 10)}T00:00:00`);
            return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(date).replace('.', '');
        };
        const entryDateTableLabel = (value) => {
            const match = String(value || '').slice(0, 10).match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!match) return '';
            const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
            const month = new Intl.DateTimeFormat('es-MX', { month: 'long' }).format(date);
            return `${String(match[3]).padStart(2, '0')} de ${month.charAt(0).toLocaleUpperCase('es-MX')}${month.slice(1)} del ${match[1]}`;
        };
        const entryActions = (id) => `<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown" title="Opciones" aria-label="Acciones de entrada"><i class="ti ti-dots-vertical fs-6"></i></a><div class="dropdown-menu dropdown-menu-end"><a href="javascript:void(0)" class="dropdown-item pointer" data-entry-detail="${escapeEntry(id)}"><i class="ti ti-eye me-1"></i>Detalle</a>${entriesSection.dataset.canEdit === '1' ? `<a href="javascript:void(0)" class="dropdown-item pointer" data-entry-edit="${escapeEntry(id)}"><i class="ti ti-pencil me-1"></i>Editar</a>` : ''}${entriesSection.dataset.canDelete === '1' ? `<a href="javascript:void(0)" class="dropdown-item pointer text-danger" data-entry-delete="${escapeEntry(id)}"><i class="ti ti-trash me-1"></i>Eliminar</a>` : ''}</div></div>`;
        const entryCells = (movement) => [
            Number(movement.id) || 0,
            `<span data-order="${escapeEntry(movement.fecha)}">${escapeEntry(movement.fecha_formateada || entryDateTableLabel(movement.fecha))}</span>`,
            escapeEntry(movement.producto),
            escapeEntry(movement.ubicacion),
            `<span class="text-success fw-semibold">+${escapeEntry(formatEntryQuantity(movement.cantidad))}</span>`,
            escapeEntry(movement.proveedor || '—'),
            escapeEntry(movement.referencia || '—'),
            entryActions(movement.id),
        ];
        const normalizeEntryMovement = (movement) => {
            const row = movement?.movimiento && typeof movement.movimiento === 'object' ? movement.movimiento : movement;
            if (!row || typeof row !== 'object') return null;
            const fecha = String(row.fecha ?? row.fecha_entrada ?? '').slice(0, 10);
            const cantidad = Number(row.cantidad ?? row.piezas ?? row.total ?? 0);
            return {
                ...row,
                id: Number(row.id ?? row.id_movimiento ?? 0),
                fecha,
                fecha_formateada: String(row.fecha_formateada ?? '') || entryDateTableLabel(fecha),
                producto: String(row.producto ?? row.productos ?? row.nombre ?? ''),
                ubicacion: String(row.ubicacion ?? row.estacion ?? ''),
                cantidad: Number.isFinite(cantidad) ? cantidad : 0,
                proveedor: String(row.proveedor ?? ''),
                referencia: String(row.referencia ?? ''),
            };
        };
        const createEntryRow = (movement) => {
            const tr = document.createElement('tr');
            tr.dataset.entryRow = String(movement.id);
            tr.dataset.entryDetailJson = JSON.stringify(movement);
            entryCells(movement).forEach((content) => {
                const td = document.createElement('td');
                td.innerHTML = content == null ? '' : content;
                tr.append(td);
            });
            return tr;
        };
        if (table && window.jQuery && jQuery.fn.DataTable) {
            entriesTable = jQuery(table).DataTable({
                autoWidth: false,
                stateSave: false,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'desc']],
                language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
                columnDefs: [
                    { targets: [0], orderable: true, searchable: false, className: 'text-center', render: (data, type, _row, meta) => type === 'display' ? meta.row + meta.settings._iDisplayStart + 1 : Number(data) || 0 },
                    { targets: [1, 3, 4, 5, 6], className: 'text-center' },
                    { targets: [7], orderable: false, searchable: false, className: 'text-center text-nowrap' },
                ],
            });
            entriesTable.on('draw', () => {
                if (window.bootstrap?.Dropdown) table.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((button) => window.bootstrap.Dropdown.getOrCreateInstance(button));
            });
        }
        const initializeEntrySelects = () => {
            if (!window.jQuery || !jQuery.fn.select2 || !entryModal) return;
            [productSelect, entryForm?.querySelector('[data-entry-provider]'), destinationsSelect].filter(Boolean).forEach((select) => {
                const $select = jQuery(select);
                if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
                $select.select2({
                    width: '100%',
                    language: 'es',
                    placeholder: select.multiple ? 'Selecciona una o varias ubicaciones' : 'Selecciona una opción',
                    allowClear: !select.multiple,
                    dropdownParent: jQuery(entryModal),
                    templateResult: (option) => option.text,
                    templateSelection: (option) => option.text,
                });
            });
            if (productSelect) {
                jQuery(productSelect).off('change.refaccionesEntries').on('change.refaccionesEntries', () => {
                    productId.value = productSelect.value;
                    markEntryControl(productSelect, false);
                });
            }
            if (destinationsSelect) {
                jQuery(destinationsSelect).off('change.refaccionesEntries').on('change.refaccionesEntries', () => {
                    markEntryControl(destinationsSelect, false);
                    if (entryForm.dataset.editing) return;
                    renderDestinationQuantities();
                });
                if (entryForm.dataset.editing) {
                    destinationsWrap?.classList.add('d-none');
                    quantitiesSection?.classList.add('d-none');
                    singleEditDestination?.classList.remove('d-none');
                } else {
                    // Rebuild the rows on open as well, including browser-restored selections.
                    renderDestinationQuantities();
                }
            }
        };
        const updateTotal = () => {
            if (!quantityTotal) return;
            const total = [...(quantitiesContainer?.querySelectorAll('[data-entry-quantity]') || [])]
                .reduce((sum, input) => sum + (Number(input.value) > 0 ? Number(input.value) : 0), 0);
            quantityTotal.textContent = formatEntryQuantity(total);
        };
        const renderDestinationQuantities = () => {
            if (!destinationsSelect || !quantitiesContainer) return;
            const previousQuantities = new Map(
                [...quantitiesContainer.querySelectorAll('[data-entry-quantity]')]
                    .map((input) => [input.name, input.value])
            );
            const selected = [...destinationsSelect.selectedOptions];
            quantitiesContainer.replaceChildren();
            selected.forEach((option) => {
                const row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-3';
                const label = document.createElement('span');
                label.className = 'flex-grow-1';
                label.textContent = option.textContent;
                const input = document.createElement('input');
                input.className = 'form-control text-end';
                input.type = 'number';
                input.min = '0.01';
                input.step = '0.01';
                input.placeholder = 'Piezas';
                input.name = `cantidades[${option.value}]`;
                input.value = previousQuantities.get(input.name) || '';
                input.dataset.entryQuantity = '';
                input.dataset.required = '';
                input.style.maxWidth = '150px';
                input.addEventListener('input', () => { input.classList.remove('is-invalid'); updateTotal(); });
                row.append(label, input);
                quantitiesContainer.append(row);
            });
            quantitiesSection?.classList.toggle('d-none', selected.length === 0);
            updateTotal();
        };
        const markEntryControl = (control, invalid) => {
            control.classList.toggle('is-invalid', invalid);
            if (window.jQuery && jQuery.fn.select2 && jQuery(control).hasClass('select2-hidden-accessible')) {
                const selection = jQuery(control).next('.select2').find('.select2-selection');
                selection.toggleClass('is-invalid border-danger', invalid);
                selection.attr('aria-invalid', invalid ? 'true' : 'false');
            }
        };
        const clearEntryErrors = () => entryForm?.querySelectorAll('.is-invalid').forEach((control) => markEntryControl(control, false));

        const rowForEntry = (id) => table?.querySelector(`[data-entry-row="${CSS.escape(String(id))}"]`) || null;
        const entryFromRow = (id) => {
            const row = rowForEntry(id);
            if (!row) return null;
            try { return JSON.parse(row.dataset.entryDetailJson || '{}'); } catch (_) { return null; }
        };
        const showEntryDetail = (movement) => {
            if (!movement) return;
            const detailModal = document.getElementById('modalRefaccionesEntradaDetalle');
            const put = (selector, value) => { const element = detailModal?.querySelector(selector); if (element) element.textContent = String(value ?? '').trim() || 'Sin información'; };
            put('[data-entry-detail-folio]', movement.folio);
            put('[data-entry-detail-date]', entryDateLabel(movement.fecha));
            put('[data-entry-detail-product]', movement.producto);
            put('[data-entry-detail-location]', movement.ubicacion);
            put('[data-entry-detail-quantity]', formatEntryQuantity(movement.cantidad));
            put('[data-entry-detail-provider]', movement.proveedor);
            put('[data-entry-detail-reference]', movement.referencia);
            put('[data-entry-detail-notes]', movement.notas);
            bootstrap.Modal.getOrCreateInstance(detailModal).show();
        };
        const resetEntryForm = () => {
            if (!entryForm) return;
            entryForm.reset();
            delete entryForm.dataset.editing;
            delete entryForm.dataset.editLocation;
            entryForm.dataset.endpoint = `${root.dataset.baseUrl}/movimientos/guardar`;
            entryForm.querySelector('.modal-title').innerHTML = '<i class="ti ti-download me-1"></i>Nueva entrada';
            entryForm.querySelector('[type="submit"]').innerHTML = '<i class="ti ti-check me-1"></i>Guardar entrada';
            if (productSelect) productSelect.disabled = false;
            if (destinationsSelect) destinationsSelect.disabled = false;
            if (fixedDestination) fixedDestination.disabled = false;
            destinationsWrap?.classList.remove('d-none');
            singleEditDestination?.classList.add('d-none');
            productId.value = '';
            quantitiesContainer?.replaceChildren();
            quantitiesSection?.classList.add('d-none');
            if (quantityTotal) quantityTotal.textContent = '0';
            clearEntryErrors();
            if (window.jQuery && jQuery.fn.select2) jQuery(entryForm).find('select').val(null).trigger('change');
        };
        const openEntryEdit = (movement) => {
            if (!movement || !entryForm) return;
            resetEntryForm();
            entryForm.dataset.editing = String(movement.id);
            entryForm.dataset.editLocation = String(movement.id_estacion_destino || '');
            entryForm.dataset.endpoint = `${root.dataset.baseUrl}/movimientos/actualizar-entrada`;
            entryForm.querySelector('.modal-title').innerHTML = '<i class="ti ti-pencil me-1"></i>Editar entrada';
            entryForm.querySelector('[type="submit"]').innerHTML = '<i class="ti ti-check me-1"></i>Guardar cambios';
            productId.value = String(movement.id_refaccion || '');
            if (productSelect) {
                productSelect.value = String(movement.id_refaccion || '');
                productSelect.disabled = false;
                jQuery(productSelect).trigger('change.select2');
            }
            if (destinationsSelect) {
                destinationsSelect.value = [String(movement.id_estacion_destino || '')];
                destinationsSelect.disabled = true;
                jQuery(destinationsSelect).trigger('change');
                destinationsWrap?.classList.add('d-none');
                quantitiesSection?.classList.add('d-none');
                singleEditDestination?.classList.remove('d-none');
                if (singleEditDestinationName) singleEditDestinationName.textContent = movement.ubicacion || 'Sin ubicación';
                if (fixedQuantity) fixedQuantity.value = movement.cantidad;
            } else if (fixedDestination && fixedQuantity) {
                fixedDestination.disabled = true;
                fixedQuantity.value = movement.cantidad;
            }
            const dateField = entryForm.elements.namedItem('fecha');
            if (dateField) dateField.value = String(movement.fecha || '').slice(0, 10);
            const providerSelect = entryForm.querySelector('[data-entry-provider]');
            if (providerSelect) {
                const match = [...providerSelect.options].find((option) => option.textContent.trim().toLocaleLowerCase() === String(movement.proveedor || '').trim().toLocaleLowerCase());
                providerSelect.value = match?.value || '';
                jQuery(providerSelect).trigger('change');
            }
            entryForm.elements.namedItem('referencia').value = movement.referencia || '';
            entryForm.elements.namedItem('notas').value = movement.notas || '';
            if (quantityTotal) updateTotal();
            bootstrap.Modal.getOrCreateInstance(entryModal).show();
        };

        const deleteEntry = async (movement) => {
            const actions = window.Alpine?.$data(entriesSection);
            if (!movement || !actions?.deleteAction) {
                window.Notify?.error('No se pudo iniciar la eliminación de la entrada.');
                return false;
            }
            const response = await actions.deleteAction({
                url: `${root.dataset.baseUrl}/movimientos/eliminar-entrada`,
                id: movement.id,
                name: `${movement.folio} · ${movement.producto}`,
                table: null,
            });
            if (!response?.success) return false;
            const row = rowForEntry(movement.id);
            if (entriesTable && row) entriesTable.row(row).remove().draw(false);
            else row?.remove();
            return true;
        };

        entrySectionActions();
        function entrySectionActions() {
            entriesSection.addEventListener('click', async (event) => {
                const detailAction = event.target.closest('[data-entry-detail]');
                if (detailAction) { event.preventDefault(); showEntryDetail(entryFromRow(detailAction.dataset.entryDetail)); return; }
                const editAction = event.target.closest('[data-entry-edit]');
                if (editAction) { event.preventDefault(); openEntryEdit(entryFromRow(editAction.dataset.entryEdit)); return; }
                const deleteAction = event.target.closest('[data-entry-delete]');
                if (!deleteAction) return;
                event.preventDefault();
                await deleteEntry(entryFromRow(deleteAction.dataset.entryDelete));
            });
        }

        entryModal?.addEventListener('shown.bs.modal', initializeEntrySelects);
        entryModal?.addEventListener('hidden.bs.modal', resetEntryForm);
        fixedQuantity?.addEventListener('input', () => fixedQuantity.classList.remove('is-invalid'));
        entryForm?.addEventListener('input', (event) => {
            if (event.target.matches('[data-required]')) markEntryControl(event.target, false);
        });
        entryForm?.addEventListener('change', (event) => {
            if (event.target.matches('[data-required]')) markEntryControl(event.target, false);
        });
        entryForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = entryForm.querySelector('[type="submit"]');
            productId.value = productSelect?.value || '';
            const invalid = [...entryForm.querySelectorAll('[data-required]')].filter((control) => {
                if (control.disabled || control.closest('.d-none')) return false;
                const value = control.multiple ? [...control.selectedOptions].length : String(control.value ?? '').trim();
                const number = control.type === 'number' && value !== '' ? Number(value) : null;
                const fails = !value || (number !== null && (!Number.isFinite(number) || number <= 0));
                markEntryControl(control, fails);
                return fails;
            });
            if (invalid.length) {
                if (window.Notify) Notify.error('Completa los campos obligatorios y revisa las cantidades.');
                invalid[0].focus();
                return;
            }
            submit.disabled = true;
            try {
                const editing = Boolean(entryForm.dataset.editing);
                const formData = new FormData(entryForm);
                if (editing) {
                    formData.set('id', entryForm.dataset.editing);
                    formData.set('producto_id', productSelect?.value || productId?.value || '');
                    const editingQuantity = fixedQuantity || quantitiesContainer?.querySelector('[data-entry-quantity]');
                    formData.set('cantidad', editingQuantity?.value || '');
                } else if (fixedDestination && fixedQuantity) formData.append(`cantidades[${fixedDestination.value}]`, fixedQuantity.value);
                const response = await axios.post(entryForm.dataset.endpoint, formData);
                if (!response.data?.success) throw new Error(response.data?.message || 'No se pudo registrar la entrada.');
                if (editing) {
                    const movement = response.data.movimiento;
                    movement.fecha = String(movement.fecha || entryForm.elements.namedItem('fecha')?.value || '').slice(0, 10);
                    movement.id_estacion_destino = Number(entryForm.dataset.editLocation || entriesSection.dataset.entryContextId);
                    const row = rowForEntry(movement.id);
                    if (row) {
                        row.dataset.entryDetailJson = JSON.stringify(movement);
                        // These rows are initialized from HTML. Set the new array data and redraw
                        // directly; invalidate() can re-read the old DOM source and leave stale
                        // cell data (including the date) in DataTables' cache.
                        if (entriesTable) entriesTable.row(row).data(entryCells(movement)).draw(false);
                    }
                } else {
                    const responseRows = response.data.movimientos ?? response.data.movimiento ?? [];
                    const movements = (Array.isArray(responseRows) ? responseRows : [responseRows])
                        .map(normalizeEntryMovement)
                        .filter((movement) => movement && movement.id > 0);
                    movements.forEach((movement) => {
                        const tr = createEntryRow(movement);
                        if (entriesTable) entriesTable.row.add(tr);
                        else table?.tBodies[0]?.prepend(tr);
                    });
                    if (entriesTable && movements.length) entriesTable.draw(false);
                }
                if (window.Notify) Notify.success(response.data.message || 'Entrada registrada.');
                bootstrap.Modal.getOrCreateInstance(entryModal).hide();
            } catch (error) {
                if (window.Notify) Notify.error(error.response?.data?.message || error.message || 'No se pudo registrar la entrada.');
            } finally {
                submit.disabled = false;
            }
        });
    }
});
