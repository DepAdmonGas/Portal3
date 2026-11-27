# Checador V2 — Directiva expresa del propietario: acceso de Portal3 a datos

**Fecha:** 2026-11-18  
**Origen:** instrucción expresa del responsable del proyecto  
**Ámbito:** Checador V2 en Portal3  
**Estado:** DIRECTIVA DEL PROPIETARIO PARA NUEVAS IMPLEMENTACIONES; CONCILIACIÓN CONTRACTUAL PENDIENTE DONDE HAYA CONFLICTOS FROZEN  
**No es:** una modificación silenciosa de Architecture Freeze V1.0, J1/J2 o contratos FROZEN.

## Decisión inequívoca

**Portal3 NO debe comunicarse directamente con la API V2 del Checador por HTTP para sus módulos de consulta y administración.** No presentar `Portal3 backend → API V2` como arquitectura obligatoria ni proponer agregar clientes JWT/Guzzle/HTTP como solución a errores de las vistas de Checador.

Flujo operativo previsto:

```text
Lector → API V2 (ingesta, validación y motor según contratos) → MariaDB canónica
Portal3 backend ↔ MariaDB canónica (lecturas y escrituras administrativas autorizadas)
Portal3 navegador ↔ Portal3 backend (sin acceso directo a MariaDB ni a la API V2)
```

La API V2 mantiene sus responsabilidades de ingesta, integridad, interpretación y cálculo autorizados. **Portal3 no debe duplicar ni inventar** evaluaciones de jornada, estados de completitud, resultados de incidencias o decisiones propiedad de API/Motor. Debe leer las proyecciones persistidas que estén definidas y autorizadas. Si una proyección o contrato SQL requerido no existe, registrar el bloqueo; no sustituirlo mediante HTTP a API por conveniencia ni inventar un esquema.

## Regla obligatoria para Codex y futuras modificaciones en Portal3

1. Antes de tocar un módulo Checador V2, identificar su fuente SQL canónica, esquema real, roles, permisos, alcances y dueño de escritura/interpretación.
2. Usar las conexiones y patrones institucionales existentes de Portal3 (PDO/Eloquent/MariaDB) para **las operaciones SQL expresamente autorizadas**. Servir los resultados desde controladores propios; no permitir conexiones SQL desde navegador.
3. **No crear, reutilizar ni proponer llamadas Portal3 → API V2 por HTTP**, directas ni por un servicio genérico, como arreglo de M01–M11. No ampliar los gates de POST a API.
4. Respetar roles Dirección/Encargado, acceso por localidad, CSRF, transacciones, auditoría, control de concurrencia, idempotencia y readback. No asumir que lectura SQL concede escritura SQL.
5. Si un contrato congelado ordena el transporte contrario, **detener esa parte**, identificar sección exacta y preparar la revisión contractual pertinente; **no implementar el transporte HTTP antiguo** ni modificar el congelado unilateralmente.
6. Conservar diferenciados fallos SQL, datos ausentes, permisos insuficientes y resultados no persistidos. Nunca transformar una falla en «catálogo vacío».

## Contradicciones documentales localizadas (evidencia histórica, no nuevas instrucciones)

- `docs/v2/CHECADOR_V2_PORTAL3_P3G_INCIDENCE_ADMINISTRATIVE_CONTRACT_V1.0.md`, §12.1: **“Portal3 Backend → API Only”**. Es un contrato `FROZEN_OWNER_APPROVED`. Requiere nueva versión o addendum autorizado antes de cambios de esas operaciones; no borrar ni reescribir la prueba histórica.
- `docs/status/CHECADOR_V2_PORTAL3_STATUS.md`: contiene descripciones históricas de lectores API HTTP; reflejan implementaciones anteriores, **no** una directiva de arquitectura para trabajo nuevo.
- `docs/architecture/CHECADOR_V2_PORTAL3_SCHEDULE_COMPLETENESS_FREEZE_J2_V1.0.md`: menciona cliente API Portal3 en la secuencia J4, pero es `FROZEN`, de alcance conceptual. Requiere conciliación si se altera su transporte.
- `docs/v2/CHECADOR_V2_COMODINES_DIRECCION_ONLY_ADDENDUM_DRAFT_V1.2.md`: ya aprueba un escritor SQL de Portal3 y el consumo posterior por API. Esa decisión demuestra que no existe una prohibición general de SQL directo desde Portal3.
- `docs/audits/checador-mvp-direct-sql-read-implementation-addendum-2026-10-21.md`: documenta una lectura directa SQL aprobada para reporte raw.

## Incidencia M04, 18 de noviembre de 2026

La ruta `/departamento-operativo/recursos-humanos/checador/horarios-turnos` usaba `ChecadorDailyReadService::getShiftTemplates()` → `GET /api/v2/shift-templates`; recibió HTTP 500 desde API. La misma tabla `op_rh_v2_turnos_plantillas` en Plesk no tiene `scope_id`, aunque el servicio API lo utiliza. **Ninguno de esos hechos autoriza una migración o un cambio de esquema**; el arreglo autorizado para Portal3 es diseñar un lector SQL seguro contra el esquema verificado, con permisos y pruebas focales. Los flujos de escritura de turnos necesitan definición y aprobación específica, no una sustitución ciega.

## Alcance y gobernanza

Esta directiva evita **nuevas implementaciones erróneas** de Portal3 → API mientras se concilian contratos. **No afirma que el código actual haya sido migrado**: los métodos HTTP existentes y sus regresiones deben inventariarse y remediarse por módulo con autorización y pruebas. Los contratos `FROZEN` siguen siendo antecedentes versionados hasta su sustitución formal; `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md` permanece intacto, compartido por API y Lector. No hacer alteraciones de API, Lector, MariaDB de Plesk ni despliegues por esta directiva.
