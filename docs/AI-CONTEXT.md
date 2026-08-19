# AI Context — Portal3

## Proyecto

Portal3 es una aplicación empresarial PHP 8.2+,
migrada parcialmente desde sistemas legacy.

## Arquitectura

- PHP 8.2+
- Framework propio
- Eloquent
- FastRoute
- PHP templates
- Alpine.js
- Axios
- MySQL

## Regla principal

NO modificar código sin auditar primero el contexto existente.

## Estado actual

### Seguridad

Estado: AUDITORÍA / ESTABILIZACIÓN

Fuente:
docs/security/security-audit.md

Roadmap:
docs/security/security-roadmap.md

### Legacy

Portal3 proviene de sistemas legacy.
Una funcionalidad ausente en Portal3 NO debe asumirse como inexistente.

Antes de crear funcionalidad:
1. buscar en Portal3;
2. buscar documentación;
3. revisar legacy;
4. comparar comportamiento;
5. documentar diferencias.

### Trabajo colaborativo

Otro desarrollador trabaja actualmente en Portal3.

Antes de modificar:

- revisar git status;
- revisar git diff;
- revisar commits recientes;
- no sobrescribir cambios;
- no reformatear archivos no relacionados;
- mantener cambios pequeños;
- evitar cambios masivos;
- no modificar funcionalidades fuera del alcance.

## Regla de seguridad

Nunca corregir una vulnerabilidad global sin evaluar primero:

- dependencias;
- frontend;
- endpoints afectados;
- regresiones;
- compatibilidad legacy;
- pruebas.

## Regla de evidencia

No afirmar que algo existe solamente porque aparece en documentación.

Clasificar:

CONFIRMED
PARTIALLY CONFIRMED
POTENTIAL
UNCONFIRMED
FALSE POSITIVE

## Regla de cambios

Preferir:

cambio pequeño
→ prueba
→ validación
→ documentación
→ siguiente cambio

Nunca:

gran refactor
→ muchas modificaciones
→ esperar que funcione.

## Estado actual

Último trabajo:
[A COMPLETAR]

Último hallazgo:
[A COMPLETAR]

Último cambio:
[A COMPLETAR]

Siguiente tarea:
[A COMPLETAR]

Bloqueos:
[A COMPLETAR]