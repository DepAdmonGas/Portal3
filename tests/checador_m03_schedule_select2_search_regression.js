'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(
    'public/assets/js/departamento-operativo/2-recursos-humanos/checador.habitual-schedule.init.js',
    'utf8',
);
const start = source.indexOf('    const normalizeScheduleSearch = (value) =>');
const end = source.indexOf('    const loadScheduleAsset =', start);
assert.ok(start >= 0 && end > start, 'search matcher functions are present in the M03 script');

const definitions = source.slice(start, end);
const { normalizeScheduleSearch, scheduleSelectMatcher } = vm.runInNewContext(
    `${definitions}\n({ normalizeScheduleSearch, scheduleSelectMatcher })`,
);
const cases = [
    ['Turno1', 'TURNO 1 · 07:00–16:00'],
    ['Turno 2', 'Turno 2 · 11:00–20:00'],
    ['Descanso', 'Descanso explícito'],
    ['Facturista', 'Facturista · TURNO 1 · 08:00–17:00'],
    ['Facturista', 'FACTURÍSTA · Turno 1'],
];

for (const [query, label] of cases) {
    const result = scheduleSelectMatcher({ term: query }, { id: 'kept', text: label });
    assert.equal(result?.id, 'kept', `${query} matches ${label}`);
    process.stdout.write(`PASS ${query} matches ${label}\n`);
}

assert.equal(scheduleSelectMatcher({ term: 'turno3' }, { id: 'x', text: 'Turno 2' }), null);
assert.equal(normalizeScheduleSearch('Á É Í Ó Ú Ñ'), 'aeioun');
process.stdout.write('PASS accents and nonmatching turns are handled\n');
