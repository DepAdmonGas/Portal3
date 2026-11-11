'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const sourcePath = 'public/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js';
let componentFactory = null;
const document = {
    addEventListener(eventName, callback) {
        if (eventName === 'alpine:init') callback();
    },
};
const context = {
    document,
    window: {},
    Alpine: {
        data(name, factory) {
            if (name === 'm04TurnCreation') componentFactory = factory;
        },
    },
};

vm.runInNewContext(fs.readFileSync(sourcePath, 'utf8'), context, { filename: sourcePath });
assert.equal(typeof componentFactory, 'function', 'M04 Alpine component is registered');
const component = componentFactory();

for (const [input, expected] of [
    ['00:00', '12:00 am'],
    ['12:00', '12:00 pm'],
    ['13:00', '01:00 pm'],
    ['14:00', '02:00 pm'],
    ['16:00', '04:00 pm'],
    ['20:00', '08:00 pm'],
    ['23:59', '11:59 pm'],
    ['07:00:00', '07:00 am'],
]) {
    assert.equal(component.formatVisibleTime(input), expected, `${input} renders as ${expected}`);
}
assert.equal(component.formatVisibleTime('06:00', 1), '06:00 am (+1 día)', 'overnight marker is preserved');
assert.equal(component.formatVisibleTime('24:00'), '24:00', 'invalid times are not silently normalized');
assert.equal(component.formatTime('14:00'), '14:00', 'internal form/payload formatter remains 24-hour');
const payloadSegments = component.buildSegmentsFrom('14:00', '20:00', '', '', {});
assert.equal(payloadSegments[0].hora_inicio_local, '14:00:00', 'writer segment still uses 24-hour time');
assert.equal(payloadSegments[0].hora_fin_local, '20:00:00', 'writer end segment still uses 24-hour time');

const orderAttributes = new Map();
const cell = {
    hasAttribute: name => orderAttributes.has(name),
    setAttribute: (name, value) => orderAttributes.set(name, value),
};
const visibleTime = {
    dataset: { m04Time24: '06:00', m04DayOffset: '1' },
    textContent: '',
    closest: selector => selector === 'td' ? cell : null,
};
component.formatTableTimes({ querySelectorAll: selector => selector === '[data-m04-time24]' ? [visibleTime] : [] });
assert.equal(visibleTime.textContent, '06:00 am (+1 día)', 'table display keeps the overnight marker');
assert.equal(orderAttributes.get('data-order'), '1800', 'table ordering uses chronological 24-hour minutes');

const markup = component.endpointMarkup({ hora_fin_local: '06:00:00', dia_offset_fin: 1 }, 'hora_fin_local', 'dia_offset_fin');
assert.match(markup, /data-m04-time24="06:00"/);
assert.match(markup, /data-m04-day-offset="1"/);

console.log('M04 12-hour display regression: PASS');
