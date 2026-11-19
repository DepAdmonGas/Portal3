<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = (string) file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.habitual-schedule.init.js');
$view = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php');
$style = (string) file_get_contents($root . '/public/assets/css/departamento-operativo/2-recursos-humanos/checador-schedule-select2.css');
$assertions = [
    'seven habitual day selects keep their original weekday names and values' =>
        str_contains($view, 'name="schedule[<?= htmlspecialchars($weekdayCode')
        && str_contains($view, 'value="REST"'),
    'habitual, replacement and temporary template selects are scoped to their own Bootstrap modals' =>
        str_contains($script, "['#habitual-schedule-assignment-modal', 'select[name^=\"schedule[\"]']")
        && str_contains($script, "'#habitual-schedule-replacement-modal', '#habitual-replacement-source, #habitual-replacement-template'")
        && str_contains($script, "'#temporary-schedule-assignment-modal', '#temporary-assignment-template'"),
    'Select2 search folds accents, case and whitespace while retaining full option text' =>
        str_contains($script, ".normalize('NFD')")
        && str_contains($script, ".replace(/\\p{M}/gu, '')")
        && str_contains($script, ".toLocaleLowerCase('es-MX')")
        && str_contains($script, ".replace(/\\s+/g, '')")
        && str_contains($script, 'data.text).includes(normalizeScheduleSearch(params.term))'),
    'modal dropdown is parented to Bootstrap modal and fills the existing control width' =>
        str_contains($script, 'dropdownParent: jQuery(modal)')
        && str_contains($script, "width: '100%'")
        && str_contains($script, "modal.addEventListener('shown.bs.modal'")
        && str_contains($script, "modal.addEventListener('hidden.bs.modal'"),
    'existing local jQuery, Select2 and stylesheet assets load only when the editor modal opens' =>
        str_contains($script, "loadScheduleAsset('/assets/js/vendor.min.js')")
        && str_contains($script, "loadScheduleAsset('/assets/libs/select2/dist/js/select2.full.min.js')")
        && str_contains($script, "'/assets/libs/select2/dist/css/select2.min.css'")
        && str_contains($script, "'/assets/css/departamento-operativo/2-recursos-humanos/checador-schedule-select2.css'")
        && str_contains($script, 'ensureScheduleSelect2()'),
    'M03 styles override the global inline container and misplaced arrow only inside schedule modals' =>
        str_contains($style, '#habitual-schedule-assignment-modal .select2-container')
        && str_contains($style, '#habitual-schedule-replacement-modal .select2-container')
        && str_contains($style, '#temporary-schedule-assignment-modal .select2-container')
        && str_contains($style, 'display: block !important')
        && str_contains($style, 'height: 38px !important')
        && str_contains($style, 'right: .75rem !important')
        && str_contains($style, 'background-color: var(--bs-body-bg)'),
    'locked selects remain governed by their existing disabled state' =>
        !str_contains($script, 'select.disabled = false')
        && !str_contains($script, '.prop(\'disabled\', false)'),
    'Select2 selection keeps the replacement Alpine change handler synchronized' =>
        str_contains($script, 'select2:select.m03Schedule select2:clear.m03Schedule')
        && str_contains($script, "new Event('change', { bubbles: true })"),
];

$failed = 0;
foreach ($assertions as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$passed) $failed++;
}
echo 'CHECADOR_M03_SCHEDULE_SELECT2: ' . (count($assertions) - $failed) . ' PASS / ' . $failed . ' FAIL' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
