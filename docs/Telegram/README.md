# Integración de Telegram — Portal3

Este documento explica cómo usar la integración de Telegram de forma local y cómo quedará configurada en el servidor.

## Arquitectura

- **Portal3** reutiliza el sistema existente: `TokenTelegram` (`op_token_telegram`, `estatus`, `chat_id`, `token`, `fecha_creacion`), `TelegramService` (`sendMessage/sendToken/getChatId/handleWebhookMessage/pollUpdates/setWebhook/getWebhookInfo`), rutas `/telegram/webhook` (público, requiere `X-Telegram-Bot-Api-Secret-Token` → SEC-WEBHOOK-008), `/telegram/poll` (auth), `/token-telegram/*` (auth/CSRF).
- Para las notificaciones salientes, `telegramBotToken()` selecciona el token según `APP_ENV`: `local` usa el bot local, `dev` usa el bot normal de desarrollo y `demo`/`prod` usan el bot DEMO en el servidor público (con `TELEGRAM_BOT_TOKEN` como respaldo).
- Los webhooks multi-bot de Portal3 se identifican por una ruta propia, cada uno con token y secreto independientes:
  - DEMO → `https://portal3.admongas.com.mx/telegram/webhook/demo`
  - DEV → `https://portal3.admongas.com.mx/telegram/webhook/dev`
- Ambos endpoints pertenecen al mismo servicio Portal3; no apuntan a `demo.admongas.com.mx` ni a la versión anterior.

## 1) Configurar el webhook de producción

Con `APP_ENV=prod`, Portal3 usa `TELEGRAM_BOT_TOKEN_DEMO` para los mensajes salientes (y `TELEGRAM_BOT_TOKEN` como respaldo). Esto no cambia la URL que Telegram ya tiene registrada. El webhook se configura en Telegram por bot: registrar una nueva URL reemplaza la anterior para ese mismo token.

### Configuración de perfiles

En el `.env` del servidor de Portal3:

```env
APP_ENV=prod
TELEGRAM_BOT_TOKEN=<token-de-respaldo-legacy>
TELEGRAM_BOT_TOKEN_DEMO=<token-del-bot-demo>
TELEGRAM_WEBHOOK_SECRET_DEMO=<secreto-del-webhook-demo>
TELEGRAM_BOT_TOKEN_DEV=<token-del-bot-dev>
TELEGRAM_WEBHOOK_SECRET_DEV=<secreto-del-webhook-dev>
```

No copies tokens ni secretos reales a este archivo, al repositorio, a capturas o a mensajes. Cada `TELEGRAM_WEBHOOK_SECRET_*` debe coincidir con el enviado al registrar ese bot. Portal3 valida el secreto desde el encabezado `X-Telegram-Bot-Api-Secret-Token`.

`APP_ENV=prod` describe el entorno de la aplicación y puede permanecer así mientras los webhooks DEMO y DEV comparten el host de Portal3. No cambies `APP_ENV` para seleccionar cuál bot recibió un webhook: las rutas `/demo` y `/dev` hacen esa selección.

### Mensaje de prueba desde Perfil

El botón **Enviar mensaje de prueba** requiere una vinculación verificada en `op_token_telegram` y envía el mensaje con el bot seleccionado por `APP_ENV`. Pulsa `/start` en Telegram y completa la vinculación con el token generado desde el perfil de Portal3 antes de usar el botón. En `prod` el envío usa DEMO; en `dev`, DEV; en `local`, LOCAL. Si la variable prioritaria del entorno existe pero está vacía, Portal3 ahora pasa a la siguiente opción configurada en vez de intentar enviar con un token vacío.

- `422` en la respuesta de `/token-telegram/test-notification`: la cuenta no está vinculada/verificada.
- `502`: hay una vinculación, pero la Bot API no aceptó el envío. El mensaje de respuesta distingue token ausente, error de conexión, credenciales inválidas y chat no accesible. Confirma que el bot seleccionado por `APP_ENV` sea el mismo con el que el usuario inició y vinculó su chat; el log `[Telegram] sendMessage rejected` incluye el código y descripción devueltos por Telegram, sin imprimir el token.
- Mensaje recibido: confirma el flujo saliente. Para verificar también el entrante, envía `/start` al bot y confirma su respuesta.

### URL receptora de Portal3

Para los perfiles multi-bot, usa las rutas del router de Portal3:

```text
https://portal3.admongas.com.mx/telegram/webhook/demo
https://portal3.admongas.com.mx/telegram/webhook/dev
```

El endpoint directo `https://portal3.admongas.com.mx/telegram_webhook.php` y la ruta antigua `/telegram/webhook` siguen disponibles para la configuración de un solo bot. Para los perfiles separados registra las dos rutas anteriores. Telegram debe poder acceder por HTTPS y el servidor debe enrutar las URLs de Portal3 al router de la aplicación.

### Registrar o mover el webhook

Registra cada bot por separado, usando el token y secreto de su perfil. Ejecuta el siguiente bloque una vez para DEMO y otra para DEV; introduce los valores correspondientes cuando PowerShell los solicite:

```powershell
$profile = Read-Host "Perfil a registrar (DEMO o DEV)"
$profile = $profile.Trim().ToUpperInvariant()
if ($profile -notin @("DEMO", "DEV")) { throw "Perfil inválido. Usa DEMO o DEV." }
$tokenSeguro = Read-Host "Token TELEGRAM_BOT_TOKEN_$profile" -AsSecureString
$secretoSeguro = Read-Host "Secreto TELEGRAM_WEBHOOK_SECRET_$profile" -AsSecureString
$tokenPtr = [IntPtr]::Zero
$secretoPtr = [IntPtr]::Zero
try {
    $tokenPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($tokenSeguro)
    $secretoPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secretoSeguro)
    $token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenPtr)
    $secreto = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secretoPtr)
    $base = "https://api.telegram.org/bot$token"
    $body = @{
        url = "https://portal3.admongas.com.mx/telegram/webhook/$($profile.ToLowerInvariant())"
        secret_token = $secreto
        allowed_updates = '["message"]'
    }

    Invoke-RestMethod -Method Post -Uri "$base/setWebhook" `
        -ContentType "application/x-www-form-urlencoded" -Body $body
}
finally {
    if ($tokenPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenPtr) }
    if ($secretoPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secretoPtr) }
    Remove-Variable token, secreto, base, body, tokenSeguro, secretoSeguro -ErrorAction SilentlyContinue
}
```

La respuesta debe incluir `"ok": true`. Registra DEMO y DEV por separado; no pegues tokens o secretos en la URL, historial del navegador, tickets o capturas.

Para configurar **solo DEV** desde PowerShell leyendo las variables de un `.env`, consulta la [guía independiente para registrar el webhook DEV](powershell-webhook-dev.md). El script usa `TELEGRAM_BOT_TOKEN_DEV` y `TELEGRAM_WEBHOOK_SECRET_DEV`; no registra DEMO.

### Paso a paso: registrar solo DEV

1. **Despliega primero el código en el servidor.** Deben estar disponibles las rutas `/telegram/webhook/dev` y el controlador actualizado. Incluye también el `TelegramService.php` vigente.
2. **Configura el `.env` del servidor** con el token y el secreto del bot DEV:

   ```env
   TELEGRAM_BOT_TOKEN_DEV=<token del bot DEV>
   TELEGRAM_WEBHOOK_SECRET_DEV=<secreto del webhook DEV>
   ```

   Sustituye los textos entre `< >` por valores reales; no compartas esos valores. El token DEV debe ser distinto del token DEMO.

3. **Prepara el `.env` local que leerá el script.** El script incluido usa `C:\xampp\htdocs\Portal3\.env`. Si lo ejecutas desde tu PC, ese archivo debe contener el mismo `TELEGRAM_BOT_TOKEN_DEV` y `TELEGRAM_WEBHOOK_SECRET_DEV` que el servidor. El script no modifica el `.env` remoto.
4. **Abre PowerShell y ejecuta el script:**

   ```powershell
   & 'C:\xampp\htdocs\Portal3\docs\Telegram\registrar-webhook-dev.ps1'
   ```

   El script lee las variables sin imprimirlas, registra únicamente DEV y se detiene si detecta que DEV y DEMO comparten el mismo token. Registrar el webhook reemplaza la URL anterior para ese mismo bot.

5. **Confirma la respuesta y la URL.** `Webhook was set` o `Webhook is already set` son respuestas válidas. La URL consultada debe ser `https://portal3.admongas.com.mx/telegram/webhook/dev`.
6. **Prueba el flujo entrante:** abre el bot DEV en Telegram y envíale `/start`. El bot debe responder. Revisa `pending_update_count`, `last_error_date` y `last_error_message` con `getWebhookInfo`.

Si aparece `403`, compara el secreto DEV del servidor con el usado al registrar el webhook. `404` indica que la ruta no está desplegada o no llega al router de Portal3. `422` indica que el controlador recibió el evento, pero falló al procesarlo o responder; confirma que el servidor tenga el `TelegramService.php` actualizado. No vuelvas a registrar DEMO al configurar DEV.

### Verificar el destino registrado

Consulta `getWebhookInfo` con el token de cada perfil y confirma que su `url` corresponda al sufijo DEMO o DEV y que `last_error_message` no reporte fallos:

```powershell
$tokenSeguro = Read-Host "Token del perfil DEMO o DEV que quieres consultar" -AsSecureString
$tokenPtr = [IntPtr]::Zero
try {
    $tokenPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($tokenSeguro)
    $token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenPtr)
    $base = "https://api.telegram.org/bot$token"
    $estado = Invoke-RestMethod -Uri "$base/getWebhookInfo"
    $estado.result | Select-Object url, pending_update_count, last_error_date, last_error_message | Format-List
}
finally {
    if ($tokenPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenPtr) }
    Remove-Variable token, base, estado, tokenSeguro -ErrorAction SilentlyContinue
}
```

Si `url` está vacía, ese bot aún no tiene webhook. Si apunta a otro host, registra nuevamente el perfil con su URL de Portal3.

## Estado y diagnóstico del webhook

`setWebhook` con `ok: true` confirma que Telegram guardó la URL; por sí solo no confirma que el servidor tenga la ruta desplegada ni que la aplicación responda correctamente.

Al diagnosticar una entrega:

- Una petición manual sin `X-Telegram-Bot-Api-Secret-Token` debe recibir `403`; el controlador rechaza intencionalmente solicitudes sin el secreto correcto.
- `404 Not Found` significa que el servidor devolvió una ruta no encontrada. Verifica que `routes/web.php` y `TelegramWebhookController.php` estén desplegados y que el servidor reescriba la ruta hacia Portal3.
- `422 Unprocessable Entity` significa que el controlador recibió el mensaje, pero no pudo completar su procesamiento/respuesta. Revisa que `TelegramService.php` esté actualizado y que el token del perfil en el `.env` del servidor sea el mismo bot registrado en Telegram.
- Las notificaciones que Portal3 envía al chat comprueban el flujo saliente. Para probar el entrante, envía `/start` al bot y confirma que responda.
- `last_error_message` describe el último error de entrega registrado por Telegram; puede corresponder a un intento anterior. Comprueba también `last_error_date` y `pending_update_count` después de enviar un mensaje de prueba.

En la configuración actual, el webhook DEMO apunta a `https://portal3.admongas.com.mx/telegram/webhook/demo`. El usuario confirmó que empezaron a llegar mensajes después de subir la corrección de `TelegramService.php`, que construye la URL de la Bot API como `https://api.telegram.org/bot<TOKEN>/...`. Si vuelve a aparecer un error, confirma primero que el servidor esté ejecutando ese archivo actualizado.

## 2) Webhook (endpoint receptor)

Los webhooks multi-bot pasan por las rutas públicas del router:

- `/telegram/webhook/demo` — usa `TELEGRAM_BOT_TOKEN_DEMO` y valida `TELEGRAM_WEBHOOK_SECRET_DEMO`.
- `/telegram/webhook/dev` — usa `TELEGRAM_BOT_TOKEN_DEV` y valida `TELEGRAM_WEBHOOK_SECRET_DEV`.
- `/telegram/webhook` y `public/telegram_webhook.php` — mantienen el perfil legacy de un solo bot.

**Importante sobre vinculación:** los webhooks identifican el bot y responden con su token correcto. La tabla legacy `op_token_telegram` no guarda un perfil de bot; las vinculaciones y notificaciones salientes permanecen compartidas y usan la selección legacy de `APP_ENV`. Para asociaciones y notificaciones independientes DEMO/DEV se necesita una tabla adicional y migración; no se modifica `op_token_telegram`.

El servidor debe enrutar `/telegram/webhook/demo` y `/telegram/webhook/dev` al router de Portal3 (por ejemplo, mediante la regla de reescritura existente para rutas públicas).

## 3) Pruebas locales (poller)

**Requisitos:** `APP_ENV=local` y `TELEGRAM_BOT_TOKEN_LOCAL` en `.env`.

```powershell
cd "C:/xampp/htdocs/Portal3"
php "scripts/telegram_poll_local.php"
```

El poller inicializa `Bootstrap::init()` para respetar `APP_TIMEZONE` (America/Mexico_City), evitando falsos "código expirado".

## 4) UI/UX

- **Botón generar:** deshabilitado mientras `generando` o `(estado==='pendiente' && segundos>0)`. Texto: `En espera de verificación (restante Xs)`.
- **Toast de advertencia:** al generar token (durante los 2 minutos), aparece un toast amarillo (estilo de notificaciones de Telegram) en `top-end`: "Podrás generar un nuevo código cuando el tiempo restante llegue a 0:00."
- **Actualización automática:** el componente consulta `/token-telegram/status` cada 2500 ms. Al detectar el cambio real a `verificado` (solo cuando el usuario acaba de autorizar en Telegram), muestra **una sola vez** el toast verde central "Telegram verificado" y actualiza la card instantáneamente (sin recargar manualmente). Si se entra al módulo ya verificado o se recarga la página (F5), el toast **no** vuelve a aparecer.

## 5) Archivos relevantes

- `public/telegram_webhook.php` — Webhook público (necesario)
- `scripts/telegram_poll_local.php` — Poller local
- `app/Views/perfil/index.php`, `public/assets/js/perfil/index.actions.init.js` — Card + lógica
- `app/Views/layouts/departamento-operativo.php` — Enlace "Mi Perfil"
- Core: `app/Services/TelegramService.php`, `app/Controllers/TelegramWebhookController.php`, `app/Controllers/TokenTelegramController.php`, `app/Models/Operativo/TokenTelegram.php`, `app/Helpers/helpers.php`
