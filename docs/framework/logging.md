# Logging

## Propósito

Documentar el sistema de logging de Portal3.

## Alcance

`app/Core/Logger.php`, Monolog, niveles, formato, almacenamiento.

---

## Implementación

### **Confirmado** — `app/Core/Logger.php`

- Librería: **Monolog 3** (`monolog/monolog`)
- Patrón: Singleton estático
- Instancia única compartida en toda la aplicación

---

## Configuración (confirmado)

| Parámetro | Valor |
|---|---|
| Log path | `storage/logs/app.log` (o `LOG_PATH` en `.env`) |
| Formato | `[Y-m-d H:i:s] LEVEL: message context\n` |
| Nivel mínimo | `DEBUG` (todos los niveles) |

---

## Niveles disponibles (confirmado)

| Método | Nivel |
|---|---|
| `Logger::debug()` | DEBUG |
| `Logger::info()` | INFO |
| `Logger::notice()` | NOTICE |
| `Logger::warning()` | WARNING |
| `Logger::error()` | ERROR |
| `Logger::critical()` | CRITICAL |
| `Logger::alert()` | ALERT |
| `Logger::emergency()` | EMERGENCY |

El método `Logger::critical()` acepta tanto `string` como `Throwable` directamente.

---

## Uso confirmado en el código

| Módulo | Nivel | Evento |
|---|---|---|
| `AuthenticationService` | `info` | Login exitoso / fallido |
| `AuthenticationService` | `warning` | Contraseña legacy detectada |
| `AuthenticationService` | `warning` | 2FA inválido |
| `ErrorHandler` | `error` | Errores PHP |
| `ErrorHandler` | `critical` | Excepciones no controladas |
| `ErrorHandler` | `critical` | Errores fatales |

---

## Archivos relevantes

- `app/Core/Logger.php`
- `storage/logs/app.log`

---

## Preguntas pendientes

- TODO: ¿Existe rotación de logs configurada?
- TODO: ¿Se envían alertas (email/Telegram) ante errores críticos?
- TODO: ¿El directorio `storage/logs/` está excluido del repositorio git?
- TODO: ¿Todos los módulos usan el Logger o algunos usan `error_log()` nativo?
