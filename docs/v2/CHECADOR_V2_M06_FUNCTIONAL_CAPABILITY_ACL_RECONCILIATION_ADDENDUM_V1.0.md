# CHECADOR V2 — M06 FUNCTIONAL CAPABILITY ACL RECONCILIATION ADDENDUM V1.0

**Estado:** APROBADO POR EL PROPIETARIO PARA PORTAL3 M06
**Aprobación:** instrucción expresa del propietario del 2026-11-23.
**Ámbito:** exclusivamente autorización de lectura, creación y revisión de contenido de Incidencias M06 en Portal3.
**Contratos relacionados:** concilia P3-G §11.2 y CHECADOR_V2_M06_PORTAL3_SQL_FULL_LIFECYCLE_ADDENDUM_V1.0 §§2 y 4.
**Freeze:** no modifica `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`, J1 ni J2.

## 1. Decisión aprobada

Para las capacidades M06 indicadas en este addendum, Portal3 autoriza mediante la identidad institucional activa, puesto, acceso institucional existente a Checador y alcance de localidad canónica. No se requiere crear registros físicos adicionales para `checador-v2-incidencias` en `modulos_dpto_operativo`, `modulos_puestos_do` o `modulos_usuarios_do`.

Esta conciliación se limita a M06 y a las operaciones enumeradas en §2. No cambia `ModuloDptoOperativoService`, sus reglas globales de precedencia ni los permisos de otros módulos. Se conserva la autorización institucional existente del módulo Recursos Humanos y del submódulo Checador, junto con la resolución y validación de localidad que Portal3 ya aplica.

## 2. Capacidades y condiciones

| Capacidad M06 | Puestos autorizados | Condiciones adicionales |
|---|---|---|
| `READ` | 6 Encargado y 13 Dirección de Operaciones | Sesión activa, usuario institucional activo, permiso existente de acceso a Checador y localidad canónica dentro del alcance autorizado. |
| `CREATE` | 6 Encargado | Las mismas condiciones de sesión y alcance. Dirección permanece exclusivamente de lectura. |
| `CREATE_REVISION` | 6 Encargado | Utiliza la capacidad de creación ya autorizada y el mismo alcance; no equivale a resolver ni aprobar la incidencia. |

La autorización se vuelve a comprobar en cada solicitud en el servidor. La localidad solicitada debe estar incluida en el alcance canónico resuelto por Checador; un identificador enviado por navegador no amplía el alcance. Se mantienen las comprobaciones de sesión, CSRF, trabajador, fechas, categoría, plazo M11, transacción, auditoría, idempotencia aplicable y readback exigidas por el addendum M06 vigente.

## 3. Conciliación limitada de P3-G §11.2

Para M06 `READ`, `CREATE` y `CREATE_REVISION`, la exigencia de que el namespace físico `checador-v2-incidencias` esté registrado como módulo o como grant de puesto/usuario queda sustituida por las capacidades funcionales y condiciones de §2.

La sustitución sólo afecta la representación física del permiso. No elimina el acceso institucional existente a Checador ni su ACL de alcance. No se crean módulos, submódulos, grants, tablas o filas ACL como parte de esta decisión.

## 4. Capacidades independientes que permanecen intactas

Esta adenda no otorga por pertenencia a Checador ni por puesto:

- `RESOLVE`.
- `AUTHORIZE`, `REJECT` o `REVOKE`.
- Evaluación administrativa.
- Autorización explícita de FALTA.

Estas operaciones conservan sus autorizaciones específicas actuales conforme a P3-G y al addendum M06 de ciclo de vida. Si se propone sustituir esos namespaces físicos por capacidades basadas en puestos, cada acción requiere una decisión de negocio separada que identifique los puestos autorizados; no queda aprobada por esta adenda.

## 5. Persistencia y alcance de la aprobación

La aprobación sólo modifica la decisión de autorización de Portal3 descrita arriba. No cambia esquema o datos, no agrega registros ACL y no altera la conexión SQL, el escritor, las transacciones ni las condiciones de corte del escritor API M06. Tampoco constituye autorización de despliegue ni de habilitación de escrituras en Plesk o producción.

La adenda se interpreta junto con los contratos vigentes; en caso de conflicto, prevalece únicamente para las tres capacidades M06 enumeradas en §2 y dentro de Portal3.
