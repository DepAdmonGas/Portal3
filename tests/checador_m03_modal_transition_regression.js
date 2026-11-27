'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/departamento-operativo/2-recursos-humanos/checador.habitual-schedule.init.js'), 'utf8');

function createHarness() {
    const documentListeners = new Map();
    const visible = new Set();
    const modals = new Map();
    let overlappingModalCount = 0;
    let replacementShowCount = 0;

    const listen = (map, type, callback) => {
        if (!map.has(type)) map.set(type, []);
        map.get(type).push(callback);
    };
    const dispatch = (element, type, relatedTarget = null) => {
        const event = { type, target: element, relatedTarget, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
        for (const callback of element.listeners.get(type) || []) callback(event);
        for (const callback of documentListeners.get(type) || []) callback(event);
        return event;
    };

    class FakeModalElement {
        constructor(id) {
            this.id = id;
            this.dataset = {};
            this.isConnected = true;
            this.listeners = new Map();
            this.classList = {
                contains: (value) => value === 'show' && visible.has(this),
                add: (value) => {
                    if (value !== 'show') return;
                    if ([...visible].some((modal) => modal !== this)) overlappingModalCount++;
                    visible.add(this);
                },
                remove: (value) => { if (value === 'show') visible.delete(this); },
            };
        }
        matches(selector) { return selector.split(',').map((item) => item.trim()).includes(`#${this.id}`); }
        addEventListener(type, callback) { listen(this.listeners, type, callback); }
    }

    const ids = [
        'habitual-schedule-assignment-modal',
        'habitual-schedule-replacement-modal',
        'temporary-schedule-assignment-modal',
    ];
    for (const id of ids) {
        const modal = new FakeModalElement(id);
        modal.dataset.scheduleSelect2Bound = '1';
        modals.set(id, modal);
    }

    const instances = new Map();
    class FakeModal {
        constructor(element) { this.element = element; this.deferHide = false; }
        show(relatedTarget) {
            if (visible.has(this.element)) return;
            this.lastRelatedTarget = relatedTarget || null;
            const event = dispatch(this.element, 'show.bs.modal', relatedTarget || null);
            if (event.defaultPrevented) return;
            if (this.element.id === 'habitual-schedule-replacement-modal') replacementShowCount++;
            this.element.classList.add('show');
            dispatch(this.element, 'shown.bs.modal', relatedTarget || null);
        }
        hide() {
            if (this.deferHide) return;
            if (!visible.has(this.element)) return;
            const event = dispatch(this.element, 'hide.bs.modal');
            if (event.defaultPrevented) return;
            this.element.classList.remove('show');
            dispatch(this.element, 'hidden.bs.modal');
        }
        static getInstance(element) { return instances.get(element) || null; }
        static getOrCreateInstance(element) {
            if (!instances.has(element)) instances.set(element, new FakeModal(element));
            return instances.get(element);
        }
    }

    const document = {
        addEventListener(type, callback) { listen(documentListeners, type, callback); },
        querySelector(selector) {
            if (selector.startsWith('#') && !selector.includes(' ')) return modals.get(selector.slice(1)) || null;
            return null;
        },
        querySelectorAll() { return []; },
    };
    const alpineFactories = new Map();
    let axios = {};
    const sandbox = {
        document,
        window: { bootstrap: { Modal: FakeModal }, jQuery: null },
        bootstrap: { Modal: FakeModal },
        Alpine: { data(name, factory) { alpineFactories.set(name, factory); } },
        console,
        location: { href: 'http://portal3.test/' },
        URL,
        Event,
        setTimeout,
        clearTimeout,
        FormData: class {},
        Element: class {},
        get axios() { return axios; },
    };
    vm.runInNewContext(source, sandbox, { filename: 'checador.habitual-schedule.init.js' });
    const alpineInit = (documentListeners.get('alpine:init') || [])[0];
    assert.equal(typeof alpineInit, 'function');
    alpineInit();

    return {
        document,
        modals,
        alpineFactories,
        visible,
        dispatch,
        setAxios(value) { axios = value; },
        getModalInstance(element) { return FakeModal.getOrCreateInstance(element); },
        metrics: () => ({ overlappingModalCount, replacementShowCount }),
    };
}

function readbackFixture(workerId, assignedIndexes = []) {
    const weekdays = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'];
    const targetDates = weekdays.map((weekday, index) => ({ weekday, date: `2026-11-${String(23 + index).padStart(2, '0')}` }));
    const existingDaysByDate = Object.fromEntries(assignedIndexes.map((index) => [targetDates[index].date, index === 1 ? 'REST' : String(100 + index)]));
    const verifiedDates = targetDates.map((item) => item.date);
    const complete = assignedIndexes.length === 7;
    const modal = { hideCount: 0, hide() { this.hideCount++; } };
    const labels = weekdays.map(() => ({ textContent: '' }));
    const selects = weekdays.map((weekday, index) => ({
        name: `schedule[${weekday}]`, value: '', disabled: true, options: [{ textContent: '' }],
        closest: () => ({ querySelector: () => labels[index] }),
    }));
    const form = {
        querySelector(selector) {
            if (selector === '[name="effective_start_date"]') return { value: '2026-11-23' };
            if (selector === '[name="workplace_id"]') return { value: '1' };
            if (selector === '[name="week_start"]') return { value: '2026-11-23' };
            return null;
        },
        querySelectorAll(selector) { return selector.startsWith('select[name^="schedule["]') ? selects : []; },
    };
    const response = { data: { ok: true, data: {
        complete: true,
        read_scope: 'EFFECTIVE_SEVEN_OCCURRENCES',
        history_complete: false,
        effective_start_date: '2026-11-23',
        target_dates: targetDates,
        verified_dates: verifiedDates,
        existing_days_by_date: existingDaysByDate,
        // The visible week can look complete while the effective seven-date readback is partial.
        existing_days: Object.fromEntries(targetDates.map((item, index) => [item.weekday, String(200 + index)])),
        habitual_complete: complete,
        replacement_sources: [],
    } } };
    return { workerId, targetDates, existingDaysByDate, selects, form, modal, response };
}

function createAssignmentComponent(harness, fixture) {
    harness.document.querySelector = (selector) => {
        if (selector === '#habitual-schedule-assignment-modal form') return fixture.form;
        if (selector === '#habitual-schedule-assignment-modal') return harness.modals.get('habitual-schedule-assignment-modal');
        return null;
    };
    const component = harness.alpineFactories.get('habitualScheduleAssignment')();
    component.workerId = String(fixture.workerId);
    component.workerName = `Worker ${fixture.workerId}`;
    component.modal = fixture.modal;
    component.$nextTick = (callback) => callback();
    component.dateForWeekday = (weekday) => fixture.targetDates.find((item) => item.weekday === weekday)?.date || '';
    return component;
}

// The successful readback scenarios use a small axios stub and the actual Alpine component methods.
async function loadFixture(workerId, assignedIndexes) {
    const harness = createHarness();
    const fixture = readbackFixture(workerId, assignedIndexes);
    let getCalls = 0;
    let postCalls = 0;
    const axios = {
        async get() { getCalls++; return fixture.response; },
        async post() { postCalls++; return { data: { ok: true, assignments: [] } }; },
    };
    harness.setAxios(axios);
    const component = createAssignmentComponent(harness, fixture);
    component.dateForWeekday = (weekday) => fixture.targetDates.find((item) => item.weekday === weekday)?.date || '';
    const assignmentModal = harness.modals.get('habitual-schedule-assignment-modal');
    component.modal = harness.getModalInstance(assignmentModal);
    component.modal.show();
    await component.loadReadback('/readback');
    return { harness, fixture, component, axios, getCalls: () => getCalls, postCalls: () => postCalls };
}

async function main() {
    for (const workerId of [558, 541]) {
        const { harness, fixture, component, getCalls } = await loadFixture(workerId, [0, 3]);
        assert.equal(getCalls(), 1, `worker ${workerId} readback requested once`);
        assert.equal(component.error, '', `worker ${workerId} partial pattern has no error`);
        assert.equal(component.effectiveDatesComplete, false, `worker ${workerId} remains eligible to fill only missing effective dates`);
        assert.deepEqual([...harness.visible], [harness.modals.get('habitual-schedule-assignment-modal')], `worker ${workerId} keeps only the assignment modal open`);
        assert.equal(fixture.selects[0].disabled, true, `worker ${workerId} persisted assignment remains locked`);
        assert.equal(fixture.selects[3].disabled, true, `worker ${workerId} second persisted assignment remains locked`);
        assert.equal(fixture.selects[1].disabled, false, `worker ${workerId} unassigned effective date can be completed`);
        assert.equal(fixture.selects[0].value, '100', `worker ${workerId} persisted value is retained`);
        assert.equal(fixture.selects[1].value, '', `worker ${workerId} missing date remains empty`);
        console.log(`PASS worker ${workerId} partial effective schedule stays in one modal and preserves existing dates`);
    }

    const full = await loadFixture(478, [0, 1, 2, 3, 4, 5, 6]);
    assert.equal(full.component.effectiveDatesComplete, true);
    assert.match(full.component.status, /siete fechas efectivas.*ya tienen asignación/i);
    assert.deepEqual([...full.harness.visible], [full.harness.modals.get('habitual-schedule-assignment-modal')], 'complete schedule does not trigger a modal transition');
    assert.ok(full.fixture.selects.every((select) => select.disabled), 'complete schedule disables all duplicate assignment inputs');
    await full.component.submit({ currentTarget: full.fixture.form });
    assert.equal(full.postCalls(), 0, 'complete schedule cannot submit duplicate writes');
    console.log('PASS complete seven-date effective readback shows an in-modal message and blocks duplicate submit');

    const harness = createHarness();
    const assignmentModal = harness.modals.get('habitual-schedule-assignment-modal');
    const replacementModal = harness.modals.get('habitual-schedule-replacement-modal');
    const assignmentInstance = harness.getModalInstance(assignmentModal);
    const replacementInstance = harness.getModalInstance(replacementModal);
    assignmentInstance.show();
    const explicitChangeAction = { dataset: { action: 'replace' } };
    replacementInstance.show(explicitChangeAction);
    replacementInstance.show(explicitChangeAction);
    assert.deepEqual([...harness.visible], [replacementModal], 'explicit change closes assignment before showing replacement');
    assert.equal(replacementInstance.lastRelatedTarget, explicitChangeAction, 'explicit replacement action retains the selected button context');
    assert.equal(harness.metrics().overlappingModalCount, 0, 'programming modals are never simultaneously visible');
    assert.equal(harness.metrics().replacementShowCount, 1, 'repeated explicit clicks do not duplicate modal opening');
    assignmentInstance.show();
    assert.deepEqual([...harness.visible], [assignmentModal], 'opening the assignment modal while replacement is visible is serialized');
    assignmentInstance.show();
    assignmentInstance.hide();
    assert.equal(harness.visible.size, 0, 'closing the assignment modal removes the last backdrop state');
    for (const dismissal of ['X', 'Cancel', 'Escape']) {
        replacementInstance.show({ dismissal });
        replacementInstance.hide();
        assert.equal(harness.visible.size, 0, `${dismissal} leaves no visible modal or backdrop state`);
    }
    assert.equal(harness.metrics().overlappingModalCount, 0);
    console.log('PASS explicit replacement action is serialized; repeated opens and closes leave no overlapping modal/backdrop');

    const transitionHarness = createHarness();
    const openingModal = transitionHarness.modals.get('habitual-schedule-assignment-modal');
    const deferredReplacement = transitionHarness.modals.get('habitual-schedule-replacement-modal');
    const openingInstance = transitionHarness.getModalInstance(openingModal);
    openingInstance.show();
    openingInstance.deferHide = true;
    transitionHarness.getModalInstance(deferredReplacement).show({ action: 'explicit-change' });
    assert.deepEqual([...transitionHarness.visible], [openingModal], 'pending transition keeps the second modal hidden');
    openingInstance.deferHide = false;
    transitionHarness.dispatch(openingModal, 'shown.bs.modal');
    assert.deepEqual([...transitionHarness.visible], [deferredReplacement], 'queued modal opens only after the first opening transition can close');
    assert.equal(transitionHarness.metrics().overlappingModalCount, 0, 'transition race never displays two modals');
    console.log('PASS a pending Bootstrap open transition is serialized without a second backdrop');

    const deferredHarness = createHarness();
    const deferredFixture = readbackFixture(558, [0]);
    let resolveReadback;
    deferredHarness.setAxios({ get: () => new Promise((resolve) => { resolveReadback = resolve; }) });
    const deferredComponent = createAssignmentComponent(deferredHarness, deferredFixture);
    const pendingReadback = deferredComponent.loadReadback('/readback');
    deferredComponent.reset();
    resolveReadback(deferredFixture.response);
    await pendingReadback;
    assert.equal(deferredComponent.workerId, '', 'closed modal reset invalidates the late readback request');
    assert.equal(Object.keys(deferredComponent.existingDaysByDate).length, 0, 'late AJAX response cannot repopulate the closed modal');
    console.log('PASS late AJAX readback cannot restore stale modal state after close');
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
