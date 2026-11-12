<?php

declare(strict_types=1);

// A managed worktree may share the primary checkout's ignored vendor/ tree.
// Keep application classes resolved from this checkout under test, while
// continuing to reuse its installed Composer dependencies.
$applicationPath = dirname(__DIR__, 2) . '/app/';
$composerLoaderFound = false;
foreach (spl_autoload_functions() ?: [] as $autoload) {
    $loader = is_array($autoload) ? ($autoload[0] ?? null) : null;
    if ($loader instanceof Composer\Autoload\ClassLoader) {
        $loader->setPsr4('App\\', [$applicationPath]);
        $composerLoaderFound = true;
        break;
    }
}

if (!$composerLoaderFound) {
    throw new RuntimeException('Composer autoloader was not initialized before the Portal3 test bootstrap.');
}
