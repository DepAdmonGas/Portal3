# Base de Datos — Arquitectura

## Propósito

Documentar cómo Portal3 se conecta y accede a la base de datos.

## Alcance

Configuración, ORM, convenciones de tablas y acceso a datos.

---

## Conexión

### **Confirmado**

- Driver: configurado via `DB_CONNECTION` en `.env` (esperado: `mysql`)
- Host: `DB_HOST`
- Base de datos: `DB_DATABASE`
- Charset: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`

La inicialización ocurre en `public/index.php` → `Database::initialize()` via `app/Core/Database.php`.

---

## ORM

### **Confirmado**

Portal3 usa **Eloquent ORM** via `illuminate/database` (paquete standalone de Laravel, versión 12.x) con `Capsule\Manager`.

```php
$capsule->setAsGlobal();
$capsule->bootEloquent();
```

Esto hace disponible Eloquent globalmente sin necesidad de instanciar el Capsule en cada clase.

---

## Convenciones de tablas (confirmado)

- **Prefijo:** `tb_` (confirmado en `tb_usuarios`, `tb_estaciones`)
- **Timestamps:** desactivados en la mayoría de los modelos (`$timestamps = false`)
- **Primary key:** `id` entero autoincremental (estándar)
- **Keytype:** `int`

---

## Notas adicionales

- TODO: Verificar si todas las tablas usan el prefijo `tb_` o si hay excepciones.
- TODO: Documentar el esquema completo de tablas.
- TODO: Identificar relaciones foráneas en la base de datos.
- TODO: Verificar si existen índices optimizados en las tablas principales.
- TODO: Confirmar si se usan transacciones en operaciones críticas.

---

## Archivos relevantes

- `app/Core/Database.php` — Inicialización de Eloquent
- `.env` — Variables de conexión (no documentar valores)
- `app/Models/` — Todos los modelos Eloquent
