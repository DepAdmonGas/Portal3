# Documentación de Portal3

> **Versión:** Esqueleto inicial — Auditoría fase 1  
> **Generado:** 2026-08-10  
> **Estado:** Plantillas creadas. Requiere completado en fases posteriores.

---

## Propósito

Este directorio contiene la documentación técnica de **Portal3**, un sistema web desarrollado por AdmonGas para la administración integral de estaciones de gas. La documentación está estructurada para facilitar el análisis, mantenimiento, refactorización y onboarding de nuevos desarrolladores o sesiones de IA.

---

## Estructura de la documentación

| Directorio | Propósito |
|---|---|
| [`architecture/`](./architecture/README.md) | Descripción de la arquitectura general del sistema |
| [`framework/`](./framework/README.md) | Documentación del framework propio y sus componentes Core |
| [`modules/`](./modules/README.md) | Módulos funcionales del negocio |
| [`frontend/`](./frontend/README.md) | Frontend: Alpine.js, Axios, JS, CSS, librerías |
| [`architecture/database.md`](./architecture/database.md) | Esquema, relaciones, índices y convenciones de base de datos |
| [`api/`](./api/README.md) | Endpoints HTTP, autenticación y convenciones de respuesta |
| [`development/`](./development/README.md) | Configuración, entorno local, debugging y deploy |
| [`refactoring/`](./refactoring/README.md) | Deuda técnica, mejoras propuestas y roadmap |
| [`decisions/`](./decisions/README.md) | Architecture Decision Records (ADRs) |
| [`audits/`](./audits/README.md) | Auditorías de arquitectura, seguridad, rendimiento y calidad |
| [`MULTIESTACION/`](./MULTIESTACION/README.md) | Sistema de multiestación: selector de estaciones por módulo |

---

## Arquitectura (confirmado)

Portal3 utiliza una arquitectura MVC-like con capas adicionales:

- **PHP 8.2+** con Composer y autoloading PSR-4
- **Framework propio** construido sobre paquetes Composer conocidos
- **Eloquent ORM** (vía `illuminate/database`) para acceso a base de datos
- **FastRoute** para routing HTTP
- **JWT + Sesión PHP** para autenticación dual
- **Vistas PHP nativas** con sistema de layouts

Ver: [`architecture/overview.md`](./architecture/overview.md)

---

## Framework

El sistema utiliza un framework propio ubicado en `app/Core/` con 22 clases que implementan:
routing, DI container, middlewares, sesiones, autenticación JWT, CSRF, logging, rate limiting y renderizado de vistas.

Ver: [`framework/README.md`](./framework/README.md)

---

## Módulos identificados

El sistema contiene aproximadamente 128 controladores organizados en módulos funcionales de negocio. Los principales identificados son:

- Autenticación y usuarios
- Personal y puestos
- Empresa y estaciones
- SASISOPA (módulo regulatorio)
- SGM (Sistema de Gestión)
- Reportes diarios y ventas
- Calibración y verificación
- Capacitación interna/externa
- Seguridad de contratistas
- Mantenimiento correctivo/preventivo
- Configuración del sistema
- Integración con Telegram

Ver: [`modules/README.md`](./modules/README.md)

---

## Frontend

El frontend utiliza PHP templates + JavaScript vanilla + librerías externas. No usa framework JS moderno (React, Vue, etc.).

Librerías confirmadas: Bootstrap 5, Alpine.js, Axios, ApexCharts, DataTables, FullCalendar, Quill, Select2, SweetAlert2, SignaturePad, SimpleBar.

Ver: [`frontend/README.md`](./frontend/README.md)

---

## Base de datos

Acceso vía Eloquent Capsule. Tablas confirmadas con prefijo `tb_`. MySQL con charset `utf8mb4`.

Ver: [`database/README.md`](./database/README.md)

---

## API

El sistema no es una API REST pura. Expone endpoints HTTP mixtos (HTML + JSON) accesibles mediante rutas definidas en `routes/web.php`.

Ver: [`api/README.md`](./api/README.md)

---

## Desarrollo

Ver: [`development/README.md`](./development/README.md)

---

## Refactorización

Ver: [`refactoring/README.md`](./refactoring/README.md)

---

## Auditorías

Ver: [`audits/README.md`](./audits/README.md)

---

## Decisiones arquitectónicas

Ver: [`decisions/README.md`](./decisions/README.md)

---

## Cómo mantener esta documentación

1. Todo documento debe distinguir entre **Confirmado**, **Inferido** y **Desconocido**.
2. No incluir secretos, credenciales ni valores sensibles.
3. Al completar un `TODO`, indicar la fecha y fuente de la información.
4. Mantener referencias al código con rutas relativas al proyecto (ej: `app/Core/Router.php`).
5. Los ADRs se crean al momento de tomar una decisión arquitectónica real.
