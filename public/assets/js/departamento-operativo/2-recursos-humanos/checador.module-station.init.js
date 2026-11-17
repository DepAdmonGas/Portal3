(function (window) {
    'use strict';

    var checadorPath = '/departamento-operativo/recursos-humanos/checador';
    var dailyPath = checadorPath + '/asistencia';
    var reportPath = checadorPath + '/reporte';

    function isCivilDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return false;
        var parsed = new Date(value + 'T00:00:00.000Z');
        return !Number.isNaN(parsed.getTime()) && parsed.toISOString().slice(0, 10) === value;
    }

    function selectedLocalityId(value) {
        // Both option groups carry canonical op_rh_localidades IDs. Their
        // selector prefixes only preserve group identity for the context POST.
        var match = /^(?:estacion|depto)_([1-9][0-9]*)$/.exec(value || '');
        return match ? match[1] : null;
    }

    function buildUrl(currentUrl, selectorValue) {
        var url = new URL(currentUrl, window.location.origin);
        var pathname = url.pathname.replace(/\/$/, '');
        var isDaily = pathname === dailyPath;
        var isReport = pathname === reportPath;
        if (!isDaily && !isReport) return url.toString();

        var nextStationId = selectedLocalityId(selectorValue);
        if (nextStationId === null) {
            // The empty selection is explicit: return to the directory after
            // the shared selector clears the saved module context.
            return new URL(checadorPath, window.location.origin).toString();
        }

        var previousStationId = url.searchParams.get('workplace_id');
        var stationChanged = previousStationId !== nextStationId;
        // The shared selector has already persisted this authorized canonical
        // locality through /api/module-context/set. Normal navigation reloads
        // from session context; the server validates and normalizes legacy
        // workplace_id links before rendering M01.
        url.searchParams.delete('workplace_id');

        if (isDaily) {
            // Attendance filters are submitted through the existing JSON POST
            // endpoint. The ordinary M01 navigation path carries no filters.
            ['date', 'worker_id', 'snapshot_ref', 'page', 'per_page']
                .forEach(function (key) { url.searchParams.delete(key); });
        } else {
            var fromDate = url.searchParams.get('from_date') || '';
            var toDate = url.searchParams.get('to_date') || '';
            var validRange = isCivilDate(fromDate)
                && isCivilDate(toDate)
                && fromDate <= toDate
                && (Date.parse(toDate + 'T00:00:00.000Z') - Date.parse(fromDate + 'T00:00:00.000Z')) <= 2592000000;
            if (nextStationId === null || !validRange) {
                url.searchParams.delete('from_date');
                url.searchParams.delete('to_date');
            }
            if (nextStationId === null) {
                url.searchParams.delete('worker_id');
            } else if (!/^[1-9][0-9]*$/.test(url.searchParams.get('worker_id') || '')) {
                url.searchParams.delete('worker_id');
            }
            if (nextStationId === null || stationChanged) {
                url.searchParams.delete('source_workplace_id');
                url.searchParams.delete('source_work_date');
                url.searchParams.delete('source_worker_id');
            }
            url.searchParams.delete('export');
        }

        return url.toString();
    }

    window.ChecadorStationNavigation = { buildUrl: buildUrl };

    // module-station-selector.js declares a classic-script global lexical
    // class; it does not publish that class as window.ModuleStationSelector.
    var selectorApi = typeof ModuleStationSelector !== 'undefined'
        ? ModuleStationSelector
        : window.ModuleStationSelector;
    var currentPath = window.location.pathname.replace(/\/$/, '');
    if (!selectorApi || (currentPath !== dailyPath && currentPath !== reportPath)) return;

    // Persist the server-authorized context first, then replace the URL so its
    // explicit workplace_id cannot override that context on the next request.
    selectorApi.init('checador', {
        customReload: function (selector) {
            var url = buildUrl(window.location.href, selector.selector ? selector.selector.value : '');
            window.location.assign(url);
        }
    });
})(window);
