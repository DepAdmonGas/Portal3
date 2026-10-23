(function (window) {
    'use strict';

    var dailyPath = '/departamento-operativo/recursos-humanos/checador';
    var reportPath = dailyPath + '/reporte';

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
        var previousStationId = url.searchParams.get('workplace_id');
        var stationChanged = previousStationId !== nextStationId;

        if (nextStationId === null) {
            url.searchParams.delete('workplace_id');
        } else {
            url.searchParams.set('workplace_id', nextStationId);
        }

        if (isDaily) {
            var date = url.searchParams.get('date') || '';
            if (nextStationId === null || !isCivilDate(date)) {
                url.searchParams.delete('date');
            }
            if (nextStationId === null || stationChanged) {
                url.searchParams.delete('worker_id');
            } else if (!/^[1-9][0-9]*$/.test(url.searchParams.get('worker_id') || '')) {
                url.searchParams.delete('worker_id');
            }
            url.searchParams.delete('snapshot_ref');
            url.searchParams.delete('page');
            url.searchParams.delete('per_page');
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
    if (!selectorApi || currentPath !== reportPath) return;

    // Keep the advanced screen's existing reload behavior unchanged. The
    // report needs a URL replacement because its current URL contains the
    // explicit workplace_id that would otherwise win over saved session state.
    selectorApi.init('checador', {
        customReload: function (selector) {
            var url = buildUrl(window.location.href, selector.selector ? selector.selector.value : '');
            window.location.assign(url);
        }
    });
})(window);
