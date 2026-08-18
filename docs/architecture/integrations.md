# Integraciones Externas

## Propósito

Documentar las integraciones de Portal3 con sistemas y servicios externos.

## Alcance

Telegram, Email, APIs externas identificadas.

---

## Telegram

### **Confirmado**

Portal3 integra Telegram como canal de notificaciones/alertas.

| Componente | Archivo |
|---|---|
| Servicio | `app/Services/TelegramService.php` |
| Controlador de webhook | `app/Controllers/TelegramWebhookController.php` |
| Controlador de tokens | `app/Controllers/TokenTelegramController.php` |
| Helper de token | `app/Helpers/helpers.php` → `telegramBotToken()` |

El sistema soporta dos bots:
- Bot de producción (`TELEGRAM_BOT_TOKEN` en `.env`)
- Bot de demo/desarrollo (`TELEGRAM_BOT_TOKEN_DEMO` en `.env`)

La selección se hace automáticamente según `APP_ENV`.

---

## Email

### **Confirmado**

Portal3 usa **PHPMailer** (`phpmailer/phpmailer 7.1`) para el envío de correos.

| Componente | Archivo |
|---|---|
| Servicio | `app/Services/EmailService.php` |

- TODO: Documentar configuración SMTP (variables de entorno usadas)
- TODO: Identificar qué módulos envían correos y en qué circunstancias

---

## Otros

- TODO: ¿Existe integración con alguna API de facturación o SAT?
- TODO: ¿Existe integración con algún proveedor de pagos?
- TODO: ¿Existe integración con algún sistema externo de reportes?
- TODO: ¿Se consultan APIs gubernamentales (CRE, ASEA, SAT)?

---

## Archivos relevantes

- `app/Services/TelegramService.php`
- `app/Controllers/TelegramWebhookController.php`
- `app/Services/EmailService.php`
