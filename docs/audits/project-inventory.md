# Inventario Inicial del Proyecto

## Información General

- **Nombre:** Portal3
- **Total de archivos PHP:** ~5550 (incluyendo vendor)
- **Archivos PHP propios:** 943 (sin vendor)
- **Archivos de vistas PHP:** 208

## Directorios Principales

| Directorio | Propósito |
|---|---|
| `app/` | Código de la aplicación |
| `public/` | Assets, uploads y entry point (`index.php`) |
| `routes/` | Archivo de rutas web |
| `storage/` | Logs y archivos generados |
| `vendor/` | Dependencias de Composer |
| `docs/` | Documentación técnica |

## Módulos Identificados en Backend

- `Controllers/` (128 controladores)
- `Core/` (22 clases del framework)
- `DTO/` (1 DTO)
- `Helpers/` (2 archivos)
- `Middleware/` (3 middlewares)
- `Models/` (Múltiples subdirectorios, ~270+ modelos)
- `Renderers/` (Generación de PDF)
- `Repositories/` (2 repositorios)
- `Services/` (55 servicios)
- `Views/` (208 vistas, organizadas en 44 subdirectorios/módulos funcionales)

## Dependencias (Composer)

- `nikic/fast-route`
- `firebase/php-jwt`
- `vlucas/phpdotenv`
- `monolog/monolog`
- `filp/whoops`
- `respect/validation`
- `illuminate/database`
- `php-di/php-di`
- `guzzlehttp/guzzle`
- `nesbot/carbon`
- `ramsey/uuid`
- `dompdf/dompdf`
- `setasign/fpdf`
- `bacon/bacon-qr-code`
- `phpmailer/phpmailer`
- `phpoffice/phpspreadsheet`

## Configuración y Scripts

- `.env` (Configuración de entorno)
- `composer.json`
- `public/.htaccess`

## Bases de Datos / SQL / Migraciones

- Uso de Eloquent. No se encontraron archivos de migración (TODO: confirmar).

## Integraciones

- Telegram (bot)
- Email (PHPMailer)
