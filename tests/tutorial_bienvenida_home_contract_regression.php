<?php

declare(strict_types=1);

function tutorial_home_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

$routes = $read('routes/web.php');
$homeController = $read('app/Controllers/HomeController.php');
$tutorialController = $read('app/Controllers/TutorialBienvenidaController.php');
$tutorialService = $read('app/Services/TutorialBienvenidaService.php');
$homeView = $read('app/Views/home/index.php');
$mainLayout = $read('app/Views/layouts/main.php');
$migration = $read('database/migrations/tb_tutorial_usuario.sql');

tutorial_home_assert(
    str_contains($routes, "'/home/tutorial/complete', Route::auth(['TutorialBienvenidaController', 'complete'])"),
    'Completion route must require the Portal3 auth middleware.'
);
tutorial_home_assert(
    str_contains($homeController, 'TutorialBienvenidaService::estado')
        && str_contains($homeController, '=== 2')
        && str_contains($homeController, "'/js/home/tutorial-bienvenida.init.js"),
    'Home must query tutorial state, restrict the multistation topic to id_gas 2, and load the experience.'
);
tutorial_home_assert(
    str_contains($homeView, 'if (!empty($tutorialDisponible))')
        && str_contains($homeView, "_tutorial-bienvenida.php")
        && str_contains($mainLayout, 'portal3TutorialReplay')
        && str_contains($mainLayout, 'data-bs-target="#portal3TutorialBienvenida"'),
    'Home must render the tutorial for available users and place its replay button in the global header.'
);
tutorial_home_assert(
    str_contains($tutorialController, '$idUsuario = Auth::id()')
        && !str_contains($tutorialController, 'Request::'),
    'The completion record must use the authenticated user, not a request supplied ID.'
);
tutorial_home_assert(
    str_contains($tutorialService, '->exists()')
        && str_contains($tutorialService, 'firstOrCreate(')
        && str_contains($tutorialService, 'catch (Throwable $e)'),
    'The service must check completion, save idempotently, and fail safely on Home reads.'
);
tutorial_home_assert(
    str_contains($tutorialService, 'capturasAnimadas')
        && str_contains($tutorialService, 'navegacion-sidebar.gif')
        && str_contains($read('app/Views/home/_tutorial-bienvenida.php'), 'data-capturas-animadas'),
    'Home must discover and pass the animated sidebar capture to the tutorial.'
);
tutorial_home_assert(
    str_contains($migration, 'CREATE TABLE IF NOT EXISTS `tb_tutorial_usuario`')
        && str_contains($migration, 'UNIQUE KEY `uq_tb_tutorial_usuario_usuario` (`id_usuario`)'),
    'The table must enforce at most one completion record per user.'
);

echo "Tutorial Home contract regression checks passed.\n";
