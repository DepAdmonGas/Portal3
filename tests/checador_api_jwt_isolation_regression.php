<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\JWTService;
use App\Models\Usuario;
use App\Services\ChecadorApiJwtConfigurationException;
use App\Services\ChecadorApiJwtService;
use App\Services\ChecadorDailyReadService;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) {
        echo "PASS {$message}\n";
        $passes++;
    } else {
        echo "FAIL {$message}\n";
        $fails++;
    }
};

$keys = ['APP_ENV', 'CHECADOR_API_REAL_POSTS_ENABLED', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO', 'CHECADOR_API_JWT_SECRET', 'JWT_SECRET'];
$previous = [];
foreach ($keys as $key) {
    $previous[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
}
$set = static function (string $key, ?string $value): void {
    if ($value === null) {
        unset($_ENV[$key]);
        putenv($key);
    } else {
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
};
$restore = static function () use ($keys, $previous): void {
    foreach ($keys as $key) {
        [$wasInEnv, $envValue, $processValue] = $previous[$key];
        if ($wasInEnv) {
            $_ENV[$key] = $envValue;
        } else {
            unset($_ENV[$key]);
        }
        if ($processValue === false) {
            putenv($key);
        } else {
            putenv($key . '=' . $processValue);
        }
    }
};

try {
    $set('APP_ENV', 'dev');
    $set('CHECADOR_ADVANCED_DEMO_ENABLED', 'false');
    $set('CHECADOR_ADVANCED_DEMO_SCENARIO', null);
    $globalSecret = str_repeat('g', 48);
    $dedicatedSecret = str_repeat('c', 48);
    $set('JWT_SECRET', $globalSecret);
    $set('CHECADOR_API_JWT_SECRET', $dedicatedSecret);

    $user = new Usuario();
    $user->id = 728;
    $apiToken = ChecadorApiJwtService::createAccessToken($user);
    $claims = (array) \Firebase\JWT\JWT::decode($apiToken, new Key($dedicatedSecret, 'HS256'));
    $assert(($claims['iss'] ?? null) === 'portal3_auth'
        && ($claims['sub'] ?? null) === 728
        && ($claims['type'] ?? null) === 'access'
        && isset($claims['iat'], $claims['exp'], $claims['jti']), 'V7C401 dedicated token has API-required claims and HS256 signature');
    $assert(($claims['exp'] - $claims['iat']) === ChecadorApiJwtService::ACCESS_TOKEN_TTL
        && ChecadorApiJwtService::ACCESS_TOKEN_TTL <= 300, 'V7C402 API token lifetime is short and bounded to five minutes');

    $portalToken = JWTService::createAccessToken(['sub' => 728]);
    $portalClaims = (array) \Firebase\JWT\JWT::decode($portalToken, new Key($globalSecret, 'HS256'));
    $assert(($portalClaims['iss'] ?? null) === 'portal3_auth' && ($portalClaims['sub'] ?? null) === 728, 'V7C403 regular Portal3 JWT still uses the unchanged global secret');
    $dedicatedRejectedByGlobal = false;
    try {
        \Firebase\JWT\JWT::decode($apiToken, new Key($globalSecret, 'HS256'));
    } catch (\Throwable) {
        $dedicatedRejectedByGlobal = true;
    }
    $assert($dedicatedRejectedByGlobal, 'V7C404 Checador token is not signed with the general Portal3 JWT secret');

    $set('CHECADOR_API_JWT_SECRET', null);
    $missingRejected = false;
    try {
        ChecadorApiJwtService::createAccessToken($user);
    } catch (ChecadorApiJwtConfigurationException) {
        $missingRejected = true;
    }
    $shortRejected = false;
    $set('CHECADOR_API_JWT_SECRET', str_repeat('x', 31));
    try {
        ChecadorApiJwtService::createAccessToken($user);
    } catch (ChecadorApiJwtConfigurationException) {
        $shortRejected = true;
    }
    $assert($missingRejected && $shortRejected, 'V7C405 missing or undersized dedicated key fails closed despite global JWT_SECRET');

    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack, 'base_uri' => 'https://api-v2-mock.invalid/', 'http_errors' => false]);
    $service = new ChecadorDailyReadService($client, null, 'https://api-v2-mock.invalid/');
    $result = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-19');
    $assert(($result['ok'] ?? true) === false
        && ($result['error']['status'] ?? 0) === 503
        && $history === [], 'V7C406 real API request is blocked before HTTP dispatch when dedicated key is absent');

    $postHistory = [];
    $postStack = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
    $postStack->push(Middleware::history($postHistory));
    $postService = new ChecadorDailyReadService(
        new Client(['handler' => $postStack, 'base_uri' => 'https://api-v2-mock.invalid/', 'http_errors' => false]),
        null,
        'https://api-v2-mock.invalid/'
    );
    // Test only: the request is bound to a Guzzle MockHandler, so this checks
    // the missing-key fail-closed path without enabling or contacting a real API.
    $set('CHECADOR_API_REAL_POSTS_ENABLED', 'true');
    $post = $postService->createDailyPunchSnapshot($user, 1, [
        'from_utc' => '2026-10-19T06:00:00Z',
        'to_utc' => '2026-10-20T06:00:00Z',
    ], 'v7c4-no-secret');
    $assert(($post['ok'] ?? true) === false && ($post['error']['status'] ?? 0) === 503 && $postHistory === [], 'V7C407 JSON POST path also fails closed before dispatch when key is absent');
    $set('CHECADOR_API_REAL_POSTS_ENABLED', 'false');

    $set('CHECADOR_ADVANCED_DEMO_ENABLED', 'true');
    $set('CHECADOR_ADVANCED_DEMO_SCENARIO', 'ready');
    $factoryCalls = 0;
    $demoHistory = [];
    $demoStack = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
    $demoStack->push(Middleware::history($demoHistory));
    $demoService = new ChecadorDailyReadService(
        new Client(['handler' => $demoStack, 'base_uri' => 'https://api-v2-mock.invalid/', 'http_errors' => false]),
        static function (Usuario $identity) use (&$factoryCalls): string { $factoryCalls++; return 'injected-test-token'; },
        'https://api-v2-mock.invalid/'
    );
    $demoResult = $demoService->resolveWorkplaceLocalDay($user, 1, '2026-10-19');
    $assert(($demoResult['ok'] ?? false) === true && $factoryCalls === 0 && $demoHistory === [], 'V7C408 demo responses stay local and bypass JWT signing/HTTP');

    $set('CHECADOR_API_JWT_SECRET', $dedicatedSecret);
    $invalidUserRejected = false;
    $invalidUser = new Usuario();
    $invalidUser->id = 0;
    try {
        ChecadorApiJwtService::createAccessToken($invalidUser);
    } catch (\InvalidArgumentException) {
        $invalidUserRejected = true;
    }
    $assert($invalidUserRejected, 'V7C409 token subject comes only from a valid authenticated Usuario identity');
} finally {
    $restore();
}

printf("CHECADOR_API_JWT_ISOLATION_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
