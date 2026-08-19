# Autorización y Permisos

## Propósito

Documentar el sistema de autorización de Portal3: cómo se controla el acceso a módulos y funciones.

## Alcance

Permisos por módulo, acceso por estación, multiestación.

---

## Sistema de permisos identificado

### **Parcialmente identificado**

El sistema de autorización de Portal3 está basado en:

1. **Módulos asignados al usuario** — gestionados por `ModuloService`
2. **Acceso por estación** — un usuario pertenece a una estación (`id_gas`)
3. **Modo multiestación** — usuarios con acceso a múltiples estaciones
4. **Departamento operativo** — acceso granular por departamento
5. **Guardas de módulo** — `BaseController::guardModuleAccess()`

---

## Modelos relacionados (confirmados)

| Modelo | Descripción |
|---|---|
| `ModulosPuestos` | Relación módulos ↔ puestos |
| `ModulosUsuarios` | Relación módulos ↔ usuarios |
| `PuestoModuloEstructura` | Estructura de permisos por puesto |
| `PuestoModuloPermiso` | Permisos específicos por puesto |
| `UsuarioModuloEstructura` | Estructura de permisos por usuario |
| `UsuarioModuloPermiso` | Permisos específicos por usuario |
| `ModuloDptoOperativo` | Módulos por departamento operativo |
| `MultiestacionPuesto` | Multiestación por puesto |
| `MultiestacionUsuario` | Multiestación por usuario |

---

## Servicios de autorización (confirmados)

| Servicio | Responsabilidad |
|---|---|
| `ModuloService` | Obtiene y guarda módulos del usuario en sesión |
| `ModuloDptoOperativoService` | Módulos por departamento operativo |
| `MultiestacionService` | Determina si usuario tiene acceso multiestación |
| `ModuleStationService` | Renderiza selector de estación y verifica disponibilidad |

---

## Guardas de módulo (confirmado)

`BaseController::guardModuleAccess($moduleKey)`:
- Verifica si el módulo está disponible para el usuario actual
- Si no: renderiza vista de módulo bloqueado
- Retorna `bool` para que el controlador decida si continuar

---

## Archivos relevantes

- `app/Services/ModuloService.php`
- `app/Services/ModuleDptoOperativoService.php`
- `app/Services/MultiestacionService.php`
- `app/Services/ModuleStationService.php`
- `app/Controllers/BaseController.php`
- `app/Models/ModulosUsuarios.php`
- `app/Models/ModulosPuestos.php`

---

## Preguntas pendientes

- TODO: ¿Cuál es la estructura exacta de permisos? ¿Hay roles?
- TODO: ¿Los permisos son por módulo completo o por acción específica?
- TODO: ¿Cómo se gestiona la asignación de módulos desde la interfaz?
- TODO: ¿Qué datos exactos almacena `ModuloService::guardarEnSesion()` en `$_SESSION`?
- TODO: ¿Cómo funciona el sistema de `id_gas = 8` que bloquea módulos (mencionado en View.php)?
