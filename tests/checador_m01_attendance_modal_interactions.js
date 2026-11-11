'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const rootPath = require('node:path').resolve(__dirname, '..');
const componentSource = fs.readFileSync(
    `${rootPath}/public/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js`,
    'utf8'
);
const viewSource = fs.readFileSync(
    `${rootPath}/app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php`,
    'utf8'
);

assert.match(viewSource, /data-checador-search-action="search"[^>]*:disabled="cargando"/);
assert.match(viewSource, /data-checador-search-action="clear"[^>]*:disabled="cargando"/);
assert.doesNotMatch(viewSource, /data-checador-search-action="(?:search|clear)"[^>]*@click=/);
assert.match(viewSource, /x-data="\{ \.\.\.actions\(\), \.\.\.checadorAttendanceSearch\(\) \}"/);
assert.match(componentSource, /this\.bindModalSelect2\(\{/);
assert.doesNotMatch(componentSource, /console\.(?:log|info|warn|debug)|\[Portal3 Checador\]|alpine\.initialized|buscar\.axios\.(?:start|response)/);
assert.match(viewSource, /aria-label="Cerrar búsqueda avanzada"[^>]*data-bs-dismiss="modal"|data-bs-dismiss="modal"[^>]*aria-label="Cerrar búsqueda avanzada"/);
assert.match(viewSource, /data-bs-dismiss="modal"[^>]*>\s*<i class="ti ti-x/);
assert.doesNotMatch(viewSource, /<form[^>]*checador-filter|@submit|form\.reset\(\)/);

let alpineInit;
let parsedRoot;
let component;
let currentRoot;
let nextTableId = 0;
const calls = [];
const modalSelect2Bindings = [];
let tableInitCount = 0;
let tableDestroyCount = 0;
let modalClosedAfterUpdatedTable = false;
let expectedTableId = null;
const browserUrl = '/departamento-operativo/recursos-humanos/checador/asistencia';

function input(value) {
    return {
        value,
        checkValidity() { return /^\d{4}-\d{2}-\d{2}$/.test(this.value); },
        reportValidity() { this.reported = true; }
    };
}

function option(value) {
    return { value, cloneNode() { return option(value); } };
}

function select(options, value) {
    return {
        options,
        value,
        disabled: false,
        replaceChildren(...newOptions) { this.options = newOptions; }
    };
}

function makeTable() {
    return {
        testId: ++nextTableId,
        isDataTable: false,
        tHead: { rows: [{ cells: Array(9).fill({}) }] },
        tBodies: [{ rows: Array(3).fill({}) }]
    };
}

function makeResults(owner, table) {
    return {
        table,
        querySelector(selector) {
            if (selector === '#checador-daily-attendance-table') return table;
            if (selector === '[data-worker-detail]') return { workerId: owner.workerId };
            return null;
        },
        replaceWith(next) {
            if (typeof owner.setResults === 'function') owner.setResults(next);
            else owner.results = next;
        }
    };
}

function makeControls(dateValue, workerValue) {
    const date = input(dateValue);
    const worker = select([option(''), option('1004'), option('1005')], workerValue);
    return {
        date,
        worker,
        querySelector(selector) {
            if (selector === 'input[name="date"]') return date;
            if (selector === 'select[name="worker_id"]') return worker;
            return null;
        }
    };
}

function makeResponseRoot(dateValue, workerValue) {
    const owner = { workerId: workerValue || 'all', setResults(next) { currentRoot.results = next; } };
    const table = makeTable();
    const results = makeResults(owner, table);
    const controls = makeControls(dateValue, workerValue);
    return {
        dataset: { attendanceState: 'success', attendanceEmptyMessage: 'No se observaron registros.' },
        table,
        results,
        controls,
        querySelector(selector) {
            if (selector === '[data-checador-attendance-results]') return results;
            if (selector === '[data-checador-filter-controls]') return controls;
            return null;
        }
    };
}

const modalListeners = new Map();
const documentReadyListeners = [];
const modal = {
    visible: false,
    classList: { contains(name) { return name === 'show' && modal.visible; } },
    addEventListener(name, callback, options) {
        const listeners = modalListeners.get(name) || [];
        listeners.push({ callback, once: !!(options && options.once) });
        modalListeners.set(name, listeners);
    },
    dispatch(name) {
        const listeners = modalListeners.get(name) || [];
        for (const entry of [...listeners]) {
            entry.callback({ type: name });
            if (entry.once) listeners.splice(listeners.indexOf(entry), 1);
        }
        modalListeners.set(name, listeners);
    },
    show() {
        modal.visible = true;
        modal.dispatch('show.bs.modal');
    },
    hide() {
        if (expectedTableId !== null) {
            modalClosedAfterUpdatedTable = currentRoot.results.table.testId === expectedTableId
                && currentRoot.results.table.isDataTable;
        }
        modal.visible = false;
        modal.dispatch('hidden.bs.modal');
    }
};

function actionButton(action) {
    const listeners = new Map();
    return {
        disabled: false,
        closest(selector) { return selector === '#container' ? currentRoot : null; },
        getAttribute(name) { return name === 'data-checador-search-action' ? action : null; },
        addEventListener(name, callback) { listeners.set(name, callback); },
        click() {
            const callback = listeners.get('click');
            return callback ? callback({ preventDefault() {} }) : undefined;
        }
    };
}
const buttons = [actionButton('search'), actionButton('clear')];

const dateInput = input('2026-10-28');
const workerSelect = select([option(''), option('1004'), option('1005')], '');
const controls = {
    date: dateInput,
    worker: workerSelect,
    querySelector(selector) {
        if (selector === 'input[name="date"]') return dateInput;
        if (selector === 'select[name="worker_id"]') return workerSelect;
        return null;
    }
};
const errorElement = {
    textContent: '',
    classList: { remove() {}, add() {} },
    style: {}
};
const initialTable = makeTable();
initialTable.isDataTable = true;
const initialResults = makeResults({ workerId: 'all', setResults(next) { currentRoot.results = next; } }, initialTable);
currentRoot = {
    dataset: { attendanceState: 'success', attendanceEmptyMessage: 'Initial' },
    _x_dataStack: [],
    results: initialResults,
    querySelector(selector) {
        if (selector === '[data-checador-filter-controls]') return controls;
        if (selector === '[data-checador-filter-modal]') return modal;
        if (selector === '[data-checador-attendance-results]') return this.results;
        if (selector === '[data-checador-search-error]') return errorElement;
        return null;
    }
};

const csrfMeta = {
    token: 'test-token',
    getAttribute() { return this.token; },
    setAttribute(_name, value) { this.token = value; }
};

function DataTable(table) {
    return {
        destroy() {
            table.isDataTable = false;
            tableDestroyCount++;
        },
        page: { info() { return { recordsDisplay: table.tBodies[0].rows.length }; } }
    };
}
DataTable.isDataTable = table => table.isDataTable;

const windowObject = {
    location: { pathname: browserUrl, search: '' },
    console: { info() {} },
    setTimeout(callback) { callback(); },
    bootstrap: { Modal: { getOrCreateInstance() { return { hide: () => modal.hide() }; } } },
    jQuery(element) { return { element, DataTable: () => DataTable(element) }; },
    initChecadorAttendanceDataTable(results) {
        const table = results.querySelector('#checador-daily-attendance-table');
        if (!table || table.tHead.rows[0].cells.length !== 9) return false;
        table.isDataTable = true;
        tableInitCount++;
        return true;
    }
};
windowObject.jQuery.fn = { DataTable };
windowObject.axios = {
    async post(url, payload, config) {
        calls.push({ url, payload: { ...payload }, config });
        const responseDate = payload.date || '2026-10-28';
        const responseWorker = payload.worker_id || '';
        parsedRoot = makeResponseRoot(responseDate, responseWorker);
        expectedTableId = parsedRoot.table.testId;
        return {
            status: 200,
            headers: { 'content-type': 'application/json; charset=UTF-8' },
            data: JSON.stringify({ success: true, html: '<div id="container"></div>' })
        };
    }
};

const documentObject = {
    addEventListener(name, callback) {
        if (name === 'alpine:init') alpineInit = callback;
        if (name === 'DOMContentLoaded') documentReadyListeners.push(callback);
    },
    querySelectorAll(selector) { return selector === '[data-checador-search-action]' ? buttons : []; },
    getElementById() { return null; },
    querySelector(selector) {
        return selector === 'meta[name="csrf-token"]' ? csrfMeta : null;
    }
};

class FakeDOMParser {
    parseFromString() {
        return { querySelector(selector) { return selector === '#container' ? parsedRoot : null; } };
    }
}

const Alpine = { data(_name, factory) { Alpine.factory = factory; } };
windowObject.Alpine = Alpine;
vm.runInNewContext(componentSource, {
    window: windowObject,
    document: documentObject,
    Alpine,
    jQuery: windowObject.jQuery,
    DOMParser: FakeDOMParser,
    console
});
alpineInit();
component = Alpine.factory();
component.$el = currentRoot;
component.bindModalSelect2 = options => modalSelect2Bindings.push(options);
currentRoot._x_dataStack = [component];
component.init();
assert.equal(modalSelect2Bindings.length, 1);
assert.equal(modalSelect2Bindings[0].modalRef, 'checador-filter-modal');
assert.equal(modalSelect2Bindings[0].selectRef, 'checadorWorkerSelect');
assert.equal(modalSelect2Bindings[0].wrapperRef, 'checadorWorkerSelectWrapper');
assert.equal(modalSelect2Bindings[0].model, 'colaborador');
assert.equal(modalSelect2Bindings[0].options.minimumResultsForSearch, 0);
assert.equal(modalSelect2Bindings[0].options.dropdownParent.element, '#checador-filter-modal');
documentReadyListeners.forEach(callback => callback());

// Bind the test controls to the Alpine model as x-model does in the browser.
Object.defineProperty(component, 'fecha', {
    configurable: true,
    get() { return dateInput.value; },
    set(value) { dateInput.value = value; }
});
Object.defineProperty(component, 'colaborador', {
    configurable: true,
    get() { return workerSelect.value; },
    set(value) { workerSelect.value = value; }
});

const click = {
    search: () => buttons[0].click(),
    clear: () => buttons[1].click(),
    cancel: () => modal.hide(),
    close: () => modal.hide(),
    reopen: () => modal.show()
};

(async function run() {
    click.reopen();
    component.fecha = '2026-10-27';
    component.colaborador = '1004';
    click.cancel();
    assert.equal(component.fecha, '2026-10-28', 'Cancel restores the applied date');
    assert.equal(component.colaborador, '', 'Cancel restores All');
    assert.equal(calls.length, 0, 'Cancel does not query');

    click.reopen();
    assert.equal(component.fecha, '2026-10-28', 'Reopen restores the applied date');
    component.fecha = '2026-10-27';
    component.colaborador = '1005';
    click.close();
    assert.equal(component.fecha, '2026-10-28', 'X close discards the date draft');
    assert.equal(component.colaborador, '', 'X close discards the collaborator draft');

    click.reopen();
    component.fecha = '2026-10-28';
    component.colaborador = '1004';
    await click.search();
    assert.deepEqual(calls[0].payload, { date: '2026-10-28', worker_id: '1004' });
    assert.equal(component.fechaAplicada, '2026-10-28');
    assert.equal(component.colaboradorAplicado, '1004');
    assert.equal(currentRoot.results.table.isDataTable, true, 'Search initializes DataTables on updated results');
    assert.equal(currentRoot.results.table.tHead.rows[0].cells.length, 9, 'Search preserves all nine columns');
    assert.ok(currentRoot.results.querySelector('[data-worker-detail]'), 'Search keeps the detail target');
    assert.deepEqual(workerSelect.options.map(item => item.value), ['', '1004', '1005'], 'Search refreshes the collaborator selector');

    click.reopen();
    assert.equal(component.colaborador, '1004', 'Reopen restores the applied collaborator');
    component.fecha = '2026-10-27';
    component.colaborador = '1005';
    await click.search();
    assert.deepEqual(calls[1].payload, { date: '2026-10-27', worker_id: '1005' });
    assert.equal(currentRoot.results.table.tHead.rows[0].cells.length, 9);
    assert.ok(currentRoot.results.querySelector('[data-worker-detail]'));
    assert.equal(tableDestroyCount, 2, 'Repeated searches destroy each prior DataTable');
    assert.equal(tableInitCount, 2, 'Repeated searches initialize one DataTable per result');

    click.reopen();
    await click.clear();
    assert.deepEqual(calls[2].payload, { date: null, worker_id: null }, 'Clear requests default date and All');
    assert.equal(component.fechaAplicada, '2026-10-28', 'Clear applies the server default date');
    assert.equal(component.colaboradorAplicado, '', 'Clear applies All');
    assert.equal(component.fecha, '2026-10-28', 'Clear updates the visible date');
    assert.equal(component.colaborador, '', 'Clear updates the visible selection');
    assert.equal(currentRoot.results.table.tHead.rows[0].cells.length, 9);
    assert.ok(currentRoot.results.querySelector('[data-worker-detail]'));
    assert.equal(tableDestroyCount, 3);
    assert.equal(tableInitCount, 3);
    assert.equal(modalClosedAfterUpdatedTable, true, 'Successful searches close only after the table is updated');
    assert.equal(windowObject.location.pathname + windowObject.location.search, browserUrl, 'Actions keep the browser URL unchanged');
    assert.equal(modalListeners.get('show.bs.modal').length, 1, 'Reopen does not duplicate modal handlers');
    assert.equal(modalListeners.get('hidden.bs.modal').length, 1, 'Close does not duplicate modal handlers');
    const displayedResults = currentRoot.results;
    windowObject.axios.post = async () => {
        const error = new Error('server detail must not reach the view');
        error.response = {
            status: 500,
            headers: { 'content-type': 'application/json' },
            data: { error: { message: 'SQLSTATE 42S02 /srv/app/database.php' } }
        };
        throw error;
    };
    await click.search();
    assert.match(component.error, /No fue posible consultar la asistencia/);
    assert.doesNotMatch(component.error, /SQLSTATE|database\.php|server detail/);
    assert.equal(currentRoot.results, displayedResults, 'A read error keeps the previous results visible');

    console.log('SIMULATED BUTTON INTERACTIONS: PASS');
    console.log('PASS Search updates results, selector, detail target, nine columns, and DataTables');
    console.log('PASS Clear queries server defaults and applies date plus All');
    console.log('PASS Cancel and X discard unapplied changes without querying');
    console.log('PASS Reopen restores applied filters after multiple searches');
    console.log('PASS URL remains unchanged and modal handlers do not accumulate');
    console.log('PASS query errors show an operational message and preserve results');
    console.log(`PASS ${calls.length} consecutive Axios POSTs; ${tableDestroyCount} DataTables destroyed / ${tableInitCount} initialized`);
    console.log('AUTHENTICATED BROWSER ACCEPTANCE: PENDING (no QA browser session available)');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
