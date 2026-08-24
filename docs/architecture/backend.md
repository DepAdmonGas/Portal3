# Arquitectura Backend

## Propósito

Documentar la arquitectura backend de Portal3: sus capas, clases principales, patrones de acceso a datos y convenciones de código.

## Alcance

PHP 8.2+, estructura de `app/`, patrones de diseño, acceso a datos, generación de respuestas.

---

## Estructura

**Confirmado:** El directorio `app/` contiene 943 archivos PHP (excluyendo vendor) organizados en:

```
app/
├── Controllers/    128 controladores
├── Core/           22 clases del framework propio
├── DTO/            1 DTO (LoginResult)
├── Helpers/        2 archivos (helpers.php, ImageHelper.php)
├── Middleware/     3 middlewares (Auth, Csrf, Guest)
├── Models/         ~200 modelos Eloquent en subdirectorios
│   ├── (raíz)      ~96 modelos generales
│   ├── Sasisopa/   123 modelos del módulo SASISOPA
│   ├── Sgm/        54 modelos del módulo SGM
│   ├── Operativo/  TODO: listar
│   ├── Sistemas/   TODO: listar
│   └── Gestoria/   TODO: listar
├── Renderers/      Generadores de PDF
│   ├── PdfMantenimientoRenderer.php
│   └── EquipoRenderers/
├── Repositories/   2 repositorios (UsuarioRepository, EstacionRepository)
├── Services/       55 servicios
└── Views/          208 archivos PHP de vistas
```

---

## Controladores

### **Confirmado**

- 128 controladores en `app/Controllers/`
- Todos extienden `BaseController` (excepto controladores especiales)
- `BaseController` inyecta `Session::get('usuario')` en `$this->filtro_usuario`
- Métodos helper en BaseController: `userId()`, `estacionId()`, `isMultiEs()`, `guardModuleAccess()`

### Convenciones de nombres (inferido del análisis)

| Sufijo | Patrón |
|---|---|
| `index` | Vista principal del módulo |
| `datatable*` | Datos para DataTables (JSON) |
| `create*` | Creación de registros (POST) |
| `update*` | Actualización de registros (POST) |
| `delete*` | Eliminación de registros (POST) |
| `*Pdf` / `*Excel` | Descarga de archivos |

---

## Servicios

### **Confirmado**

- 55 servicios en `app/Services/`
- Contienen la lógica de negocio
- No extienden clase base común (cada uno independiente)

### Servicios con responsabilidades específicas (confirmado)

| Servicio | Responsabilidad |
|---|---|
| `AuthenticationService` | Login/logout con 2FA |
| `TokenService` | Emisión y validación de JWT |
| `SessionService` | Gestión de sesión de usuario |
| `MenuService` | Construcción del menú de navegación |
| `ModuleStationService` | Selector de estación por módulo |
| `ModuloService` | Permisos y módulos de usuario |
| `MultiestacionService` | Lógica de múltiples estaciones |
| `EmailService` | Envío de correos (PHPMailer) |
| `TelegramService` | Integración con Telegram |

### Servicios de exportación (confirmado)

- `*ExcelService` — Generan archivos Excel con PHPSpreadsheet
- Ejemplos: `AceiteExcelService`, `ClienteMesExcelService`, `PersonalExcelService`

---

## Modelos

### **Confirmado**

- Todos extienden `Illuminate\Database\Eloquent\Model` (Eloquent)
- Tablas con prefijo `tb_` (confirmado en `tb_usuarios`, `tb_estaciones`)
- `$timestamps = false` en la mayoría (sin `created_at`/`updated_at` automáticos)
- Los modelos definen `$fillable` y `$guarded` explícitamente
- Uso de scopes Eloquent (ej: `scopeActivo` en `Usuario`)

### Relaciones confirmadas (muestra)

| Modelo | Relaciones |
|---|---|
| `Usuario` | `belongsTo Puestos`, `belongsTo Estacion`, `hasMany UsuariosFamiliares`, `hasMany UsuariosFormacionAcademica`, `hasMany UsuariosExperienciaLaboral`, `belongsToMany Menu` |
| `Estacion` | `hasMany Usuario` |

---

## Acceso a base de datos

### **Confirmado**

- Acceso principalmente directo a modelos Eloquent desde servicios y controladores
- Solo `UsuarioRepository` y `EstacionRepository` usan el patrón repositorio formal
- Se usa `Illuminate\Database\Capsule\Manager` (no Laravel completo)

---

## Helpers globales

### **Confirmado** — `app/Helpers/helpers.php`

| Función | Descripción |
|---|---|
| `base_url()` | Retorna `APP_URL` del entorno |
| `asset($path)` | Genera URL a `/assets/` |
| `formatearFecha($fecha)` | Fecha en formato "d de Mes del Y" |
| `formatearFechaLarga($fecha)` | Fecha con día de semana |
| `formatearFechaCorta($fecha)` | Fecha en formato `d-m-Y` |
| `formatDate($fecha)` | Fecha en formato `Y-m-d` |
| `nombremes($mes)` | Número de mes a nombre en español |
| `normalizarFecha($fecha)` | Normaliza fechas inválidas a null |
| `mkdir_safe($path)` | `mkdir` con permisos 0755 |
| `telegramBotToken()` | Token de Telegram según entorno |
| `sanitize_input($value, $type)` | Sanitización de entradas |
| `validate_input($data, $rules)` | Validación básica con reglas string |

---

## Generación de respuestas

### **Confirmado**

Dos tipos de respuesta:

1. **Vista HTML** — `View::render($view, $data, $layout)` → templates PHP
2. **JSON** — `JsonResponse::success/error/validation/...()` → respuesta estandarizada

Estructura JSON estandarizada:
```json
{
  "success": true|false,
  "type": "success|error|validation|warning|info",
  "message": "...",
  "data": {...}
}
```

---

## Logging

### **Confirmado**

- Implementado con Monolog 3 en `app/Core/Logger.php`
- Singleton estático
- Log path: `storage/logs/app.log` (configurable via `LOG_PATH` en `.env`)
- Niveles disponibles: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`
- Formato: `[Y-m-d H:i:s] LEVEL: message context`

---

## Manejo de errores

### **Confirmado**

- `APP_ENV=dev` → Whoops con página HTML interactiva
- `APP_ENV=prod` → Monolog + respuesta genérica (nunca expone detalles)
- Para peticiones POST: siempre responde JSON aunque sea error HTML
- Errores fatales capturados via `register_shutdown_function`

---

## Archivos relevantes

- `app/Controllers/BaseController.php` — Base de controladores
- `app/Core/View.php` — Sistema de vistas
- `app/Core/JsonResponse.php` — Respuestas JSON
- `app/Core/Logger.php` — Logging
- `app/Core/ErrorHandler.php` — Manejo de errores
- `app/Helpers/helpers.php` — Funciones globales

---

## Preguntas pendientes

- TODO: ¿Qué modelos existen en `app/Models/Operativo/`, `Sistemas/` y `Gestoria/`?
- TODO: ¿Los controladores validan input directamente o delegan a servicios?
- TODO: ¿Existe algún patrón de validación consistente entre controladores?
- TODO: ¿Se usan transacciones de base de datos? ¿Dónde?
- TODO: ¿Existe algún middleware de logging de peticiones?
