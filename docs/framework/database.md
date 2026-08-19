# Base de Datos — Framework

## Propósito

Documentar cómo Portal3 accede a la base de datos a través del framework.

## Alcance

Eloquent Capsule, modelos, convenciones, uso en servicios y controladores.

---

## ORM: Eloquent via Capsule

### **Confirmado**

Portal3 usa `illuminate/database` de Laravel como ORM standalone, inicializado con `Capsule\Manager`:

```php
// app/Core/Database.php
$capsule->addConnection([...]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
```

Esto hace disponible Eloquent globalmente: todos los modelos que extiendan `Illuminate\Database\Eloquent\Model` funcionan sin configuración adicional.

---

## Convenciones de modelos (confirmadas)

| Configuración | Valor estándar |
|---|---|
| Tabla | `tb_[nombre]` (prefijo `tb_`) |
| Primary key | `id` (integer, autoincrement) |
| Timestamps | `false` (desactivados) |
| Charset | `utf8mb4` |

---

## Patrones de acceso a datos

### **Confirmado**

El acceso a datos se hace de varias formas:

1. **Directamente en servicios** — `ModelName::where(...)->get()` (patrón más común)
2. **Via Repository** — solo para `Usuario` y `Estacion`
3. **Directamente en controladores** — algunos controladores acceden a modelos sin pasar por servicios

---

## Logging

### **Confirmado** — `app/Core/Logger.php`

- Usa Monolog 3
- Singleton estático
- Log en `storage/logs/app.log`
- Configurable via `LOG_PATH` en `.env`
- Niveles: debug, info, notice, warning, error, critical, alert, emergency

---

## Archivos relevantes

- `app/Core/Database.php`
- `app/Models/` — Todos los modelos
- `app/Repositories/` — UsuarioRepository, EstacionRepository
- `app/Services/` — Lógica de negocio con acceso a modelos

---

## Preguntas pendientes

- TODO: ¿Se usan transacciones de base de datos? ¿En qué operaciones?
- TODO: ¿Existen query scopes personalizados más allá de `scopeActivo`?
- TODO: ¿Existe algún mecanismo de caché de consultas?
- TODO: ¿Se usa Eloquent eager loading para prevenir N+1?
