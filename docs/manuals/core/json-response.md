# Documentación: Clase `App\Core\JsonResponse`

La clase `App\Core\JsonResponse` es la encargada de estandarizar y enviar las respuestas HTTP en formato JSON desde los controladores de la aplicación hacia el cliente (Axios, Fetch, Postman, etc.).

---

## ⚡ Características Clave

* **Terminación Inmediata (`never`):** Todos los métodos ejecutan `exit`, por lo que detienen la ejecución de PHP inmediatamente después de enviar la respuesta y los encabezados.
* **Encabezados Automáticos:** Establece de forma transparente el código de estado HTTP (`http_response_code`) y el encabezado `Content-Type: application/json; charset=UTF-8`.
* **Codificación Segura:** Utiliza `JSON_UNESCAPED_UNICODE`, `JSON_UNESCAPED_SLASHES` y `JSON_INVALID_UTF8_SUBSTITUTE` para evitar problemas con acentos, caracteres especiales y barras inclinadas en URLs.
* **Estructura Consistente:** Garantiza que el cliente siempre reciba una respuesta con las propiedades base `'success'`, `'type'` y `'message'`.

---

## 📚 Estructura Estándar de Respuesta

Salvo que se utilice `JsonResponse::custom()`, todas las respuestas estandarizadas devuelven un objeto JSON con el siguiente formato base:

```json
{
  "success": true,        // (bool)  true para 2xx, false para errores
  "type": "success",       // (string) success | error | validation | warning | info
  "message": "Mensaje...", // (string) Descripción legible para el usuario o frontend
  "..." : "..."            // (mixed)  Atributos adicionales pasados en $data
}
```

## 💻 Ejemplos de Uso

### 1. Respuestas Exitosas (200 OK)

```php
use App\Core\JsonResponse;

// Solo mensaje
// Código HTTP: 200
JsonResponse::success('Programa anual de verificación eliminado');

// Mensaje con datos adicionales
// Código HTTP: 200
JsonResponse::success('Equipo registrado correctamente.', [
    'id' => $inventario->id
]);
```
---

### 2. Respuestas de Error General (error)

```php
// Error por defecto (HTTP 400 Bad Request)
JsonResponse::error('El equipo ya fue eliminado.');

// Error con código HTTP personalizado
JsonResponse::error('La contraseña ingresada es incorrecta.', 400, [
    'intentos_restantes' => 2
]);
```
---

### 3. Error de Validación de Formularios (422 Unprocessable Entity)

```php
JsonResponse::validation('Los datos enviados no son válidos.', [
    'email' => ['El correo electrónico es obligatorio.'],
    'password' => ['La contraseña debe tener al menos 8 caracteres.']
]);
```

Salida JSON:

```Json
{
  "success": false,
  "type": "validation",
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "email": ["El correo electrónico es obligatorio."],
    "password": ["La contraseña debe tener al menos 8 caracteres."]
  }
}
```
---

### 4. Respuestas HTTP HTTP Específicas
La clase cuenta con métodos declarativos preconfigurados con sus respectivos códigos de estado HTTP estándar:

```php
// 401 Unauthorized
JsonResponse::unauthorized('Debes iniciar sesión para continuar.');

// 403 Forbidden
JsonResponse::forbidden('No tienes permisos para realizar esta acción.');

// 404 Not Found
JsonResponse::notFound('El equipo solicitado no existe.');

// 409 Conflict
JsonResponse::conflict('Ya existe una norma registrada con ese código.');

// 429 Too Many Requests
JsonResponse::tooManyRequests('Has superado el límite de peticiones permitidas.');

// 500 Internal Server Error
JsonResponse::serverError('Ocurrió un error inesperado al procesar la solicitud.');
```
---

### 5. Estructura Totalmente Personalizada (custom)
Si necesitas omitir la envoltura estándar (type, message, etc.) y devolver una estructura JSON personalizada estructurada manualmente, utiliza el método custom():

```php
JsonResponse::custom([
    'success' => true,
    'data' => [
        'id' => $inventario->id,
        'nombre' => $inventario->nombre,
        'identificacion' => $inventario->identificacion,
        'funcion' => $inventario->funcion,
        'fecha_instalacion' => optional($inventario->fecha_instalacion)?->format('Y-m-d'),
        'manuales' => $inventario->manuales->map(fn($manual) => [
            'id' => $manual->id,
            'fecha_hora' => optional($manual->fecha_hora)?->format('Y-m-d H:i'),
            'archivo' => $manual->archivo,
            'url' => '/uploads/archivos/manuales/' . $manual->archivo
        ])->values()
    ]
], 200);
```
---

### 6. Enviar Headers HTTP Personalizados (send)
Si requieres enviar encabezados HTTP adicionales (por ejemplo, para manejo de caché o descargas):

```php
JsonResponse::send(
    data: ['status' => 'active'],
    status: 200,
    headers: [
        'Cache-Control' => 'no-cache, must-revalidate',
        'X-App-Version' => '1.2.0'
    ]
);
```