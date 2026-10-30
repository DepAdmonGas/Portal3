# Registrar únicamente el webhook DEV desde PowerShell

Esta guía configura el webhook del bot DEV en Telegram. No registra ni modifica el bot DEMO.

## Antes de ejecutarlo

1. Despliega en el servidor de Portal3 las rutas y el controlador de los perfiles DEMO/DEV.
2. Asegúrate de que el `.env` que leerá el script tenga `TELEGRAM_BOT_TOKEN_DEV` y `TELEGRAM_WEBHOOK_SECRET_DEV` con los mismos valores que están configurados en el servidor.
3. Confirma que `TELEGRAM_BOT_TOKEN_DEV` sea distinto de `TELEGRAM_BOT_TOKEN_DEMO`. Cada bot solo puede tener un webhook activo; registrar una URL para un token reemplaza su URL anterior.

## Registrar DEV

El script [registrar-webhook-dev.ps1](registrar-webhook-dev.ps1) localiza el `.env` desde la carpeta del propio archivo, no imprime tokens ni secretos, registra únicamente DEV y luego consulta el estado del webhook. Si la verificación detecta que DEV y DEMO usan el mismo token, se cancela para no reemplazar accidentalmente el webhook de DEMO.

Desde la raíz del proyecto, ejecútalo así:

```powershell
& '.\docs\Telegram\registrar-webhook-dev.ps1'
```

La URL debe terminar en `/telegram/webhook/dev`. Después envía `/start` al bot DEV para probar una actualización entrante. No compartas capturas que incluyan tokens, secretos ni la URL completa de una llamada a la Bot API.
