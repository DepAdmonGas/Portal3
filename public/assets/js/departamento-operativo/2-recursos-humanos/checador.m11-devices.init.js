(() => {
    'use strict';

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[character]));

    const utcLabel = (value) => {
        if (typeof value !== 'string' || value.trim() === '') return 'Sin recepción registrada';
        return escapeHtml(value.replace('T', ' ').replace(/Z$/, ' UTC'));
    };

    const assignmentLabel = (assignment) => {
        const labels = { ASSIGNED: 'Asignado', UNASSIGNED: 'Sin asignación confirmada', UNKNOWN: 'No confirmado' };
        const label = labels[assignment?.status] || 'No confirmado';
        if (assignment?.status !== 'ASSIGNED') return label;
        const from = utcLabel(assignment.valid_from_utc);
        const to = assignment.valid_to_utc ? utcLabel(assignment.valid_to_utc) : 'Vigente';
        return `${label}<br><span class="small text-muted">${from} – ${to}</span>`;
    };

    document.addEventListener('DOMContentLoaded', () => {
        const section = document.getElementById('m11-devices');
        const tableElement = document.getElementById('m11-devices-table');
        const statusElement = document.getElementById('m11-devices-status');
        if (!section || !tableElement || !statusElement || !window.jQuery?.fn?.DataTable) return;

        const setStatus = (text, completeness = null) => {
            statusElement.textContent = text;
            statusElement.className = 'small mb-3';
            if (completeness === 'COMPLETE') statusElement.classList.add('text-success');
            else statusElement.classList.add('text-warning-emphasis');
        };

        window.jQuery(tableElement).DataTable({
            processing: true,
            serverSide: false,
            autoWidth: false,
            stateSave: false,
            pageLength: 10,
            order: [[1, 'asc']],
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
            ajax: (_request, callback) => {
                window.jQuery.ajax({
                    url: section.dataset.devicesUrl,
                    method: 'GET',
                    dataType: 'json',
                    cache: false,
                    headers: { Accept: 'application/json' },
                }).done((payload) => {
                    if (payload?.status !== 'OK' || !Array.isArray(payload.data)
                        || !['COMPLETE', 'PARTIAL', 'UNKNOWN'].includes(payload.completeness)) {
                        setStatus('No fue posible confirmar la lectura del inventario.');
                        callback({ data: [] });
                        return;
                    }
                    const count = payload.data.length;
                    const readAt = utcLabel(payload.read_at_utc);
                    if (payload.completeness === 'COMPLETE') {
                        setStatus(`Inventario completo · ${count} dispositivos · Lectura ${readAt}`, 'COMPLETE');
                    } else if (payload.completeness === 'PARTIAL') {
                        setStatus(`Inventario parcial · ${count} dispositivos confirmados · Lectura ${readAt}`);
                    } else {
                        setStatus(`No se pudo determinar si el inventario está completo · Lectura ${readAt}`);
                    }
                    callback({ data: payload.data });
                }).fail((xhr) => {
                    const message = xhr.status === 401
                        ? 'Tu sesión venció. Inicia sesión nuevamente.'
                        : xhr.status === 403
                            ? 'No tienes autorización para consultar este inventario.'
                            : 'El inventario no está disponible; no se presenta como una lista vacía.';
                    setStatus(message);
                    callback({ data: [] });
                });
            },
            columns: [
                {
                    data: 'device.uuid',
                    render: (value, type) => type === 'display' ? escapeHtml(value) : String(value ?? ''),
                },
                {
                    data: 'device.name',
                    render: (value, type) => type === 'display' ? escapeHtml(value) : String(value ?? ''),
                },
                {
                    data: 'physical_station',
                    render: (value, type) => {
                        if (type !== 'display') return String(value?.name ?? '');
                        return value?.id ? `${escapeHtml(value.name)} · ${escapeHtml(value.id)}` : 'No confirmada';
                    },
                },
                {
                    data: 'workplace',
                    render: (value, type) => {
                        if (type !== 'display') return String(value?.name ?? '');
                        return value?.mapping_status === 'VERIFIED'
                            ? `${escapeHtml(value.name)} · ${escapeHtml(value.id)}` : 'Mapeo no confirmado';
                    },
                },
                {
                    data: 'assignment',
                    render: (value, type) => type === 'display' ? assignmentLabel(value) : String(value?.status ?? ''),
                },
                {
                    data: 'device.persisted_status',
                    render: (value, type) => {
                        const labels = { ACTIVE: 'Activo', INACTIVE: 'Inactivo', UNKNOWN: 'No disponible' };
                        return type === 'display' ? (labels[value] || 'No disponible') : String(value ?? '');
                    },
                },
                {
                    data: 'last_communication.last_server_received_at_utc',
                    render: (value, type) => type === 'display' ? utcLabel(value) : String(value ?? ''),
                },
            ],
        });
    });
})();
