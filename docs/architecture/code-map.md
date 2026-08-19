# Mapa de Código

## Propósito

Inventario de directorios, clases importantes, entry points y responsabilidades del código fuente de Portal3.

## Alcance

Todos los directorios del proyecto. Excluye `vendor/`.

---

## Mapa de directorios raíz

```
Portal3/
├── public/               Directorio público (DocumentRoot)
│   ├── index.php         ENTRY POINT ÚNICO
│   ├── .htaccess         Rewrite rules
│   ├── assets/           Assets estáticos (CSS, JS, imágenes, libs)
│   └── uploads/          Archivos subidos por usuarios
├── app/                  Código de la aplicación
│   ├── Controllers/      128 controladores HTTP
│   ├── Core/             22 clases del framework propio
│   ├── DTO/              1 DTO
│   ├── Helpers/          2 archivos de helpers
│   ├── Middleware/        3 middlewares
│   ├── Models/           ~270+ modelos Eloquent
│   ├── Renderers/        Generadores de PDF
│   ├── Repositories/     2 repositorios
│   ├── Services/         55 servicios de negocio
│   └── Views/            208 templates PHP en 44 módulos
├── routes/
│   └── web.php           Todas las rutas (1506 líneas, ~160 KB)
├── storage/
│   └── logs/             Archivos de log (Monolog)
├── vendor/               Dependencias Composer (excluido de este mapa)
├── composer.json         Dependencias y autoloading
├── composer.lock
├── .env                  Variables de entorno (NO documentar valores)
├── .gitignore
└── .htaccess             Rewrite rules raíz
```

---

## Clases importantes por categoría

### Framework Core (`app/Core/`)

| Clase | Descripción |
|---|---|
| `Router` | Dispatcher HTTP (FastRoute) |
| `Route` | Helper de rutas con middlewares |
| `Kernel` | Ejecutor de middlewares |
| `Container` | DI container con autowiring |
| `Session` | Gestión de sesión PHP |
| `Auth` | Helper de autenticación |
| `View` | Renderizador de vistas |
| `JWTService` | Tokens JWT |
| `CsrfToken` | Protección CSRF |
| `Database` | Inicialización Eloquent |
| `Logger` | Logging (Monolog) |
| `ErrorHandler` | Manejo de errores |
| `JsonResponse` | Respuestas JSON estandarizadas |
| `RateLimiter` | Rate limiting por IP/sesión |

### Modelos críticos

| Modelo | Tabla | Descripción |
|---|---|---|
| `Usuario` | `tb_usuarios` | Usuario del sistema |
| `Estacion` | `tb_estaciones` | Estación de gas |
| `Menu` | TODO | Menú de navegación |
| `Modulo` | TODO | Módulos/permisos |
| `Puestos` | TODO | Puestos de trabajo |

### Servicios clave

| Servicio | Descripción |
|---|---|
| `AuthenticationService` | Login/logout con 2FA |
| `TokenService` | JWT |
| `SessionService` | Sesión de usuario |
| `MenuService` | Construcción del menú |
| `ModuloService` | Permisos |
| `ModuleStationService` | Selector de estación |
| `MultiestacionService` | Lógica multiestación |

---

## Entry points

| Entry Point | Descripción |
|---|---|
| `public/index.php` | Único entry point HTTP |
| `routes/web.php` | Definición de todas las rutas |

---

## Módulos funcionales (por directorio de vistas)

```
app/Views/
├── login/
├── home/
├── personal/
├── puestos/
├── empresa/
├── estaciones/
├── grupos/
├── modulo/
├── usuario/
├── configuracion/
├── reportediario/
├── ventas (TODO: verificar nombre)
├── sasisopa/           SASISOPA (módulo regulatorio)
├── sgm/                SGM (Sistema de Gestión)
├── auditorias/
├── capacitacioninterna/
├── capacitacionexterna/
├── cursos/
├── asistencia/
├── gafetes/
├── tarjetas/
├── seguridadcontratistas/
├── requisitoslegales/
├── incidentesaccidentes/
├── integridadmecanica/
├── preparacionemergencias/
├── monitoreoverificacionevaluacion/
├── objetivosmetasindicadores/
├── controlactividadproceso/
├── mejorespracticas/
├── controldocumentosregistros/
├── informedesempeno/
├── revisionresultados/
├── departamento-operativo/
├── cambioprecio/
├── configuracionbitacora/
├── competenciapersonalcapacitacionentrenamiento/
├── comunicacionparticipacionconsulta/
├── procedimientos/
├── sistemas/
├── seguro/
├── partials/           Componentes parciales
├── layouts/            Layouts base
└── errors/             Páginas de error
```

---

## Preguntas pendientes

- TODO: Completar responsabilidades de cada módulo funcional
- TODO: Listar clases importantes de cada módulo
- TODO: Documentar relaciones entre módulos
- TODO: Identificar módulos con mayor complejidad ciclomática
- TODO: Verificar si hay código en `public/uploads/` que no debería estar ahí
