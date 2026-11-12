# Integración de Telegram — Portal3

Este documento explica cómo usar la integración de Telegram de forma local y cómo quedará configurada en el servidor.

## Arquitectura

- **Portal3** reutiliza el sistema existente: `TokenTelegram` (`op_token_telegram`, `estatus`, `chat_id`, `token`, `fecha_creacion`), `TelegramService` (`sendMessage/sendToken/getChatId/handleWebhookMessage/pollUpdates/setWebhook/getWebhookInfo`), rutas `/telegram/webhook` (público, requiere `X-Telegram-Bot-Api-Secret-Token` → SEC-WEBHOOK-008), `/telegram/poll` (auth), `/token-telegram/*` (auth/CSRF).
- La elección del bot depende de `APP_ENV` (definido en `app/Helpers/helpers.php`):
  - `APP_ENV=local` → usa `TELEGRAM_BOT_TOKEN_LOCAL` (bot dedicado para pruebas locales). Requiere **poller**.
  - `APP_ENV=dev|demo` → usa `TELEGRAM_BOT_TOKEN_DEMO` (pruebas/QA en servidor). **Requiere webhook** hacia `https://demo.admongas.com.mx/telegram/webhook`.
  - `APP_ENV=prod` → usa `TELEGRAM_BOT_TOKEN` (productivo). **Requiere webhook** hacia `https://portal.admongas.com.mx/telegram/webhook`.
  - **Definitivo:** `portal3.admongas.com.mx` → usa webhook `https://portal3.admongas.com.mx/telegram/webhook`.

## 1) Webhook (único archivo necesario)

Se utiliza únicamente el endpoint público directo:

- `public/telegram_webhook.php` — Inicializa `vendor/autoload`, carga `.env`, ejecuta `Bootstrap::init()` + `Database::initialize()` y delega a `TelegramWebhookController::handle()`. Requiere el header `X-Telegram-Bot-Api-Secret-Token` igual a `TELEGRAM_WEBHOOK_SECRET`.

**URL a registrar en `setWebhook`:**

```
https://portal3.admongas.com.mx/telegram_webhook.php
```

También es válido usar la ruta del router `https://portal3.admongas.com.mx/telegram/webhook`, según configuración del servidor. El archivo público es suficiente y autónomo para recibir actualizaciones de Telegram.

### Configuración de `TELEGRAM_WEBHOOK_SECRET`

Definir en `.env` (servidor):
```env
TELEGRAM_WEBHOOK_SECRET=<secreto-largo-aleatorio>
```

### Registrar webhook

```php
$t = new \App\Services\TelegramService();
$t->setWebhook('https://portal3.admongas.com.mx/telegram_webhook.php');
```

## 2) Pruebas locales (poller)

**Requisitos:** `APP_ENV=local` y `TELEGRAM_BOT_TOKEN_LOCAL` en `.env`.

```powershell
cd "C:/xampp/htdocs/Portal3"
php "scripts/telegram_poll_local.php"
```

El poller inicializa `Bootstrap::init()` para respetar `APP_TIMEZONE` (America/Mexico_City), evitando falsos "código expirado".

## 3) UI/UX

- **Botón generar:** deshabilitado mientras `generando` o `(estado==='pendiente' && segundos>0)`. Texto: `En espera de verificación (restante Xs)`.
- **Toast de advertencia:** al generar token (durante los 2 minutos), aparece un toast amarillo (estilo de notificaciones de Telegram) en `top-end`: "Podrás generar un nuevo código cuando el tiempo restante llegue a 0:00."
- **Actualización automática:** el componente consulta `/token-telegram/status` cada 2500 ms. Al detectar el cambio real a `verificado` (solo cuando el usuario acaba de autorizar en Telegram), muestra **una sola vez** el toast verde central "Telegram verificado" y actualiza la card instantáneamente (sin recargar manualmente). Si se entra al módulo ya verificado o se recarga la página (F5), el toast **no** vuelve a aparecer.

## 4) Archivos relevantes

- `public/telegram_webhook.php` — Webhook público (necesario)
- `scripts/telegram_poll_local.php` — Poller local
- `app/Views/perfil/index.php`, `public/assets/js/perfil/index.actions.init.js` — Card + lógica
- `app/Views/layouts/departamento-operativo.php` — Enlace "Mi Perfil"
- Core: `app/Services/TelegramService.php`, `app/Controllers/TelegramWebhookController.php`, `app/Controllers/TokenTelegramController.php`, `app/Models/Operativo/TokenTelegram.php`, `app/Helpers/helpers.php`
