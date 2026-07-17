<?php
declare(strict_types=1);
// --------------------------------------------------------
// AUTOLOAD Y CONFIGURACIÓN INICIAL
// --------------------------------------------------------
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use Dotenv\Dotenv;
use App\Core\Bootstrap;
use App\Core\ErrorHandler;
use App\Core\Database;
use App\Core\Router;
use App\Core\Session;

// ============================================================
// SECURITY: Headers HTTP esenciales (Vulnerabilidad #3)
// ============================================================

// Prevenir clickjacking
header('X-Frame-Options: DENY');

// Prevenir MIME sniffing
header('X-Content-Type-Options: nosniff');

// Protección XSS del navegador
header('X-XSS-Protection: 1; mode=block');

// Content Security Policy - Más permisiva para desarrollo
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://cdn.ckeditor.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.ckeditor.com; img-src 'self' data: https: blob:; font-src 'self' https://cdn.jsdelivr.net data: https://cdn.ckeditor.com; connect-src 'self' https://cdn.jsdelivr.net https://unpkg.com https://cdn.ckeditor.com; frame-ancestors 'self';");

// HSTS (solo si HTTPS está configurado)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Prevenir Referer leak
header('Referrer-Policy: strict-origin-when-cross-origin');

// Control de permisos del navegador
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// ============================================================
// INICIALIZACIÓN DE SESIÓN
// ============================================================
Session::init();

// --------------------------------------------------------
// CARGAR VARIABLES DE ENTORNO (.env)
// --------------------------------------------------------
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad(); // Usa safeLoad() para evitar error si falta .env

// --------------------------------------------------------
// CONFIGURAR ZONA HORARIA Y CODIFICACIÓN
// --------------------------------------------------------
Bootstrap::init();
mb_internal_encoding('UTF-8');

// --------------------------------------------------------
// REGISTRAR MANEJADOR GLOBAL DE ERRORES
// (Usa Whoops en modo dev, y Monolog + página genérica en prod)
// --------------------------------------------------------
ErrorHandler::register();
Database::initialize();

// --------------------------------------------------------
// DESPACHAR LA RUTA PRINCIPAL
// --------------------------------------------------------
$router = new Router();
$router->dispatch();
