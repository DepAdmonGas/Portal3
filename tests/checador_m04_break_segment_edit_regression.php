<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$controllerPath = __DIR__ . '/../app/Controllers/ChecadorController.php';
$controllerSource = file_get_contents($controllerPath);
$method = new ReflectionMethod(App\Controllers\ChecadorController::class, 'm04SegmentsAreEditable');
$method->setAccessible(true);

$segment = static function (int $ordinal, string $type, string $start, string $end, int $startOffset = 0, int $endOffset = 0): array {
    return [
        'ordinal' => $ordinal,
        'tipo_segmento' => $type,
        'hora_inicio_local' => $start . ':00',
        'hora_fin_local' => $end . ':00',
        'dia_offset_inicio' => $startOffset,
        'dia_offset_fin' => $endOffset,
    ];
};

$cases = [
    'continuous daytime shift remains editable' => [
        [$segment(1, 'WORK', '07:00', '16:00')], true,
    ],
    'one explicit break is editable' => [
        [$segment(1, 'WORK', '07:00', '11:00'), $segment(2, 'BREAK', '11:00', '12:00'), $segment(3, 'WORK', '12:00', '16:00')], true,
    ],
    'overnight shift with a break remains editable' => [
        [$segment(1, 'WORK', '22:00', '23:00'), $segment(2, 'BREAK', '23:00', '23:30'), $segment(3, 'WORK', '23:30', '06:00', 0, 1)], true,
    ],
    'non-contiguous work and break segments remain invalid' => [
        [$segment(1, 'WORK', '07:00', '10:00'), $segment(2, 'BREAK', '11:00', '12:00'), $segment(3, 'WORK', '12:00', '16:00')], false,
    ],
    'overlapping break segments remain invalid' => [
        [$segment(1, 'WORK', '07:00', '11:00'), $segment(2, 'BREAK', '10:00', '12:00'), $segment(3, 'WORK', '12:00', '16:00')], false,
    ],
];

$failed = 0;
foreach ($cases as $label => [$segments, $expected]) {
    $actual = $method->invoke(null, $segments);
    $passed = $actual === $expected;
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}

$allowsRepresentableShapeChange = str_contains($controllerSource, '$editable = self::m04EditableTemplateFields($previous[\'data\'][\'segmentos\'] ?? []);')
    && str_contains($controllerSource, 'if ($editable === null) {')
    && str_contains($controllerSource, '|| !self::m04SegmentsAreEditable($segments)');
$passed = $allowsRepresentableShapeChange;
echo ($passed ? 'PASS ' : 'FAIL ') . 'versioning accepts supported segment-shape changes after validating both versions' . PHP_EOL;
$failed += $passed ? 0 : 1;

echo 'M04_BREAK_SEGMENT_EDIT: ' . (count($cases) + 1 - $failed) . '/' . (count($cases) + 1) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
