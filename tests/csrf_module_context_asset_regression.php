<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$layout = (string) file_get_contents($root . '/app/Views/layouts/departamento-operativo.php');
$assetPath = $root . '/public/assets/js/core/csrf-request-setup.js';
$asset = is_file($assetPath) ? (string) file_get_contents($assetPath) : '';
$legacyAsset = (string) file_get_contents($root . '/public/assets/js/core/csrf-http.js');
$selector = (string) file_get_contents($root . '/public/assets/js/core/module-station-selector.js');
$route = (string) file_get_contents($root . '/routes/web.php');
$controller = (string) file_get_contents($root . '/app/Controllers/ModuleContextController.php');
$middleware = (string) file_get_contents($root . '/app/Middleware/CsrfMiddleware.php');

$checks = [
    'department layout references the existing deployed CSRF setup asset after Axios and the token meta' =>
        str_contains($layout, 'js/core/csrf-request-setup.js?v=20261112-1')
        && strpos($layout, 'axios.min.js') < strpos($layout, 'name="csrf-token"')
        && strpos($layout, 'name="csrf-token"') < strpos($layout, 'csrf-request-setup.js'),
    'the requested setup asset exists and configures Axios for same-origin mutations' =>
        $asset !== ''
        && str_contains($asset, "config.headers['X-CSRF-TOKEN'] = getCsrfToken()")
        && str_contains($asset, "url.origin === window.location.origin"),
    'station context remains an authenticated POST and selector calls the same route' =>
        str_contains($route, "addRoute('POST', '/api/module-context/set', Route::auth(['ModuleContextController', 'setContext']))")
        && str_contains($selector, "axios.post('/api/module-context/set'")
        && str_contains($controller, 'ChecadorAccessService::authorize($selectedId)'),
    'the CSRF middleware validates the X-CSRF-TOKEN header for JSON requests' =>
        str_contains($middleware, "HTTP_X_CSRF_TOKEN")
        && str_contains($middleware, 'CsrfToken::validate($token)'),
    'a 419 refresh does not replay an unsafe request and both asset URLs remain idempotent' =>
        str_contains($asset, "error.response.status === 419")
        && str_contains($asset, 'window.location.reload()')
        && !str_contains($asset, 'return window.axios(error.config)')
        && !str_contains($asset, 'return axios(error.config)')
        && str_contains($asset, 'window.__portal3CsrfHttpConfigured')
        && str_contains($legacyAsset, 'window.__portal3CsrfHttpConfigured'),
];

$failures = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$passed) $failures++;
}

printf("CSRF_MODULE_CONTEXT_ASSET_RESULT: %d PASS / %d FAIL (static checks; no authenticated HTTP/browser execution)\n", count($checks) - $failures, $failures);
exit($failures === 0 ? 0 : 1);
