(function (window) {
    'use strict';

    var rootPath = '/departamento-operativo/recursos-humanos/checador';

    function isCivilDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return false;
        var parsed = new Date(value + 'T00:00:00.000Z');
        return !Number.isNaN(parsed.getTime()) && parsed.toISOString().slice(0, 10) === value;
    }

    function buildUrl(currentUrl, selectorValue, previousValue) {
        var url = new URL(currentUrl, window.location.origin);
        var pathname = url.pathname.replace(/\/$/, '');
        var isChecadorRoute = pathname === rootPath || pathname.indexOf(rootPath + '/') === 0;
        if (!isChecadorRoute) return url.toString();

        url.searchParams.delete('workplace_id');
        var nextLocality = /^(?:estacion|depto)_([1-9][0-9]*)$/.exec(selectorValue || '');
        if (!nextLocality) {
            // Clearing the selector is an explicit request for an empty
            // context; go back to the lightweight index without old filters.
            return new URL(rootPath, window.location.origin).toString();
        }

        // Station-scoped modules: a confirmed department context belongs
        // on the directory instead of reloading the station-only screen into 403.
        var stationOnlyRoutes = [
            rootPath + '/rol-comodines',
            rootPath + '/acceso-temporal',
            rootPath + '/jornadas'
        ];
        if (stationOnlyRoutes.indexOf(pathname) !== -1 && selectorValue.indexOf('depto_') === 0) {
            return new URL(rootPath, window.location.origin).toString();
        }

        var stationChanged = (previousValue || '') !== (selectorValue || '');
        if (stationChanged) {
            [
                'worker_id', 'snapshot_ref', 'snapshot_token', 'page', 'per_page',
                'source_workplace_id', 'source_work_date', 'source_worker_id',
                'export', 'dt_search', 'dt_order_column', 'dt_order_direction',
                'schedule_history_page'
            ].forEach(function (key) { url.searchParams.delete(key); });
        }

        if (pathname === rootPath) {
            // The directory no longer uses query flags to select a workspace.
            ['schedule_week', 'schedule_history', 'week_start'].forEach(function (key) {
                url.searchParams.delete(key);
            });
        } else if (pathname === rootPath + '/programacion-semanal') {
            // M03 is selected by its institutional path; the query contains
            // only the civil week filter.
            ['schedule_week', 'schedule_history', 'date', 'worker_id', 'page', 'per_page']
                .forEach(function (key) { url.searchParams.delete(key); });
            if (!isCivilDate(url.searchParams.get('week_start') || '')) {
                url.searchParams.delete('week_start');
            }
        } else if (pathname === rootPath + '/asistencia') {
            var date = url.searchParams.get('date') || '';
            if (!isCivilDate(date)) url.searchParams.delete('date');
        } else if (pathname === rootPath + '/reporte') {
            var fromDate = url.searchParams.get('from_date') || '';
            var toDate = url.searchParams.get('to_date') || '';
            var validRange = isCivilDate(fromDate) && isCivilDate(toDate)
                && fromDate <= toDate
                && (Date.parse(toDate + 'T00:00:00.000Z') - Date.parse(fromDate + 'T00:00:00.000Z')) <= 2592000000;
            if (!validRange) {
                url.searchParams.delete('from_date');
                url.searchParams.delete('to_date');
            }
            if (!['today', 'last7', 'month', 'year'].includes(url.searchParams.get('period') || '')) {
                url.searchParams.delete('period');
            }
        } else if (stationChanged) {
            // Keep only date/period selectors on other Checador workspaces.
            var safeKeys = ['date', 'period', 'year', 'month', 'week_start', 'schedule_history'];
            Array.from(url.searchParams.keys()).forEach(function (key) {
                if (!safeKeys.includes(key)) url.searchParams.delete(key);
            });
            if (!isCivilDate(url.searchParams.get('date') || '')) url.searchParams.delete('date');
        }

        return url.toString();
    }

    window.ChecadorStationNavigation = { buildUrl: buildUrl };

    // This file is included only by Checador views. Keep all cards on the same
    // institutional session context and route each confirmed change cleanly.
    var selectorApi = typeof ModuleStationSelector !== 'undefined'
        ? ModuleStationSelector
        : window.ModuleStationSelector;
    if (!selectorApi || !document.getElementById('module-station-selector-checador')) return;

    selectorApi.init('checador', {
        customReload: function (selector, previousValue) {
            var nextValue = selector.selector ? selector.selector.value : '';
            var url = buildUrl(window.location.href, nextValue, previousValue);
            window.location.assign(url);
        }
    });
})(window);
