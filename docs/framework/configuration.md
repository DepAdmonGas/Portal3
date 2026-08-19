# Configuración del Sistema

## Propósito

Documentar las variables de entorno y parámetros de configuración de Portal3.

## Alcance

Variables de `.env`, configuración de PHP, configuración de MySQL, configuración de servicios.

---

> **ADVERTENCIA DE SEGURIDAD:** Este documento NUNCA debe contener valores reales de credenciales, tokens, contraseñas, API keys o cualquier dato sensible. Solo documenta la existencia y propósito de cada variable.

---

## Variables de entorno (`.env`)

### **Confirmado** — Variables identificadas en el código fuente

#### Aplicación

| Variable | Descripción | Referenciada en |
|---|---|---|
| `APP_NAME` | Nombre de la aplicación | `ErrorHandler.php`, `Logger.php` |
| `APP_ENV` | Entorno: `dev`, `demo`, `prod` | `ErrorHandler.php`, `helpers.php` |
| `APP_URL` | URL base de la aplicación | `helpers.php`, `JWTService.php` |
| `APP_TIMEZONE` | Zona horaria PHP | `Bootstrap.php` |
| `APP_LOCALE` | Locale para Carbon | `Bootstrap.php` |
| `APP_DATE_FORMAT` | Formato de fecha por defecto | `Bootstrap.php` |

#### Base de datos

| Variable | Descripción | Referenciada en |
|---|---|---|
| `DB_CONNECTION` | Driver de BD (ej: `mysql`) | `Database.php` |
| `DB_HOST` | Host de la base de datos | `Database.php` |
| `DB_DATABASE` | Nombre de la base de datos | `Database.php` |
| `DB_USERNAME` | Usuario de la base de datos | `Database.php` |
| `DB_PASSWORD` | Contraseña de la base de datos | `Database.php` |

#### JWT y seguridad

| Variable | Descripción | Referenciada en |
|---|---|---|
| `JWT_SECRET` | Clave secreta para firmar JWT | `JWTService.php` |

#### Logging

| Variable | Descripción | Referenciada en |
|---|---|---|
| `LOG_PATH` | Ruta del archivo de log | `Logger.php` |

#### Telegram

| Variable | Descripción | Referenciada en |
|---|---|---|
| `TELEGRAM_BOT_TOKEN` | Token del bot de producción | `helpers.php` |
| `TELEGRAM_BOT_TOKEN_DEMO` | Token del bot de desarrollo/demo | `helpers.php` |

#### Otras variables (posibles)

- TODO: Confirmar variables de configuración SMTP para PHPMailer
- TODO: Confirmar si existe variable para modo debug adicional
- TODO: Verificar variables de configuración de uploads

---

## Valores por defecto (confirmados en código)

| Variable | Valor por defecto | Fuente |
|---|---|---|
| `APP_ENV` | `'prod'` | `ErrorHandler.php` |
| `APP_TIMEZONE` | `'UTC'` | `Bootstrap.php` |
| `APP_LOCALE` | `'en'` | `Bootstrap.php` |
| `APP_DATE_FORMAT` | `'Y-m-d H:i:s'` | `Bootstrap.php` |

---

## Configuración de sesión (confirmado en código)

| Parámetro | Valor |
|---|---|
| Lifetime | 90000 segundos (~25 horas) |
| Path | `/` |
| HttpOnly | `true` |
| SameSite | `Lax` |
| Secure | Solo si HTTPS activo |

---

## Archivos relevantes

- `.env` — Configuración del entorno (no commitear)
- `.gitignore` — Debe excluir `.env`
- `public/index.php` — Bootstrap y carga de variables
- `app/Core/Bootstrap.php` — Inicialización de zona horaria
- `app/Core/Database.php` — Uso de variables DB_*
- `app/Core/JWTService.php` — Uso de JWT_SECRET
- `app/Core/Logger.php` — Uso de LOG_PATH

---

## Preguntas pendientes

- TODO: ¿Existe un `.env.example` que documente las variables requeridas?
- TODO: ¿Cuáles son las variables requeridas mínimas para que el sistema funcione?
- TODO: ¿Existe configuración diferenciada para desarrollo vs producción más allá de `APP_ENV`?
- TODO: ¿Cómo se configuran las credenciales SMTP para PHPMailer?
