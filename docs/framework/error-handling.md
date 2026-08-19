# Manejo de Errores y Excepciones

## Propósito

Documentar cómo Portal3 maneja errores y excepciones.

## Alcance

`app/Core/ErrorHandler.php`, Whoops, Monolog, respuestas de error.

---

## Estrategia de manejo de errores

### **Confirmado** — `app/Core/ErrorHandler.php`

| Entorno | Mecanismo | Exposición |
|---|---|---|
| `APP_ENV=dev` | Whoops (HTML interactivo o JSON) | Detalles completos |
| `APP_ENV=prod` | Monolog + respuesta genérica | Solo log interno, nunca al usuario |

---

## En desarrollo (`APP_ENV=dev`)

- Para peticiones HTML: página Whoops visual con stack trace
- Para peticiones JSON/POST: respuesta JSON con detalles del error

---

## En producción (`APP_ENV=prod`)

- `set_error_handler` → `Logger::error()`
- `set_exception_handler` → `Logger::critical()` + respuesta genérica
- `register_shutdown_function` → captura errores fatales → `Logger::critical()`

Respuesta para peticiones JSON:
```json
{
  "success": false,
  "message": "Ha ocurrido un error. Contacte al administrador."
}
```

---

## Detección de petición JSON

**Confirmado:** Se considera petición JSON si:
- El método HTTP es POST, PUT, DELETE o PATCH
- O el header `HTTP_ACCEPT` contiene `application/json`

---

## Páginas de error personalizadas

**Confirmado:** El Router renderiza vistas PHP para errores:
- 404 → `app/Views/errors/404.php`
- 405 → `app/Views/errors/405.php`

---

## Archivos relevantes

- `app/Core/ErrorHandler.php`
- `app/Core/Logger.php`
- `app/Views/errors/`

---

## Preguntas pendientes

- TODO: ¿Existen más páginas de error (500, 403)?
- TODO: ¿Se notifica al equipo cuando ocurre un error crítico en producción?
- TODO: ¿Existe rotación de logs en producción?
