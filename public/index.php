<?php
declare(strict_types=1);
session_start();
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
