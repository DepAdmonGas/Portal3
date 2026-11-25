# Checador V2 — PIN y autorizaciones: propuesta V1.0

**Estado: propuesta pendiente de aprobación expresa y conciliación entre Portal3, API y Lector Windows.** No autoriza implementación, DDL, endpoints, permisos ni despliegue. No modifica Architecture Freeze V1.0 ni el contrato congelado `TEMPORARY_ACCESS_API_CONTRACT`.

## 1. Alcance y responsabilidades

Reutilizar la pantalla Portal3 `checador/acceso-temporal` con secciones separadas para PIN de asistencia y PIN administrativo. El PIN de asistencia conserva su contrato, tablas, estados, ACL, transporte, vigencias y respuestas existentes. El nuevo flujo administrativo será exclusivamente en línea: Portal3 emite el PIN mediante SQL en la base compartida; el Lector se comunica con API para canjearlo y ejecutar operaciones; Portal3 no llama a API por HTTP para este nuevo flujo. API valida la sesión y es la única escritora de plantillas, sujeto a aprobación de esa responsabilidad. No reactivar rutas legacy.

Los dos propósitos tienen almacenes, validadores y ámbitos separados. Un PIN o sesión administrativa nunca valida asistencia ni autoriza una marcación; el PIN de asistencia nunca abre sesión administrativa. No introducir un valor de propósito en el contrato de asistencia existente sin una conciliación aprobada.

## 2. Emisión del PIN administrativo

Portal3 debe validar sesión institucional activa, `tb_usuarios.estatus = 0`, `tb_usuarios.id_puesto = 6`, permisos efectivos específicos para emitir y operar, dispositivo activo y localidad canónica autorizada mediante mapeo inequívoco a la estación física. Los nombres de puestos del catálogo de trabajadores no son una autoridad. La localidad y el dispositivo enviados desde la pantalla son sólo solicitudes: el servidor vuelve a resolverlos. Dirección y otros usuarios autorizados sólo consultan, salvo decisión explícita posterior.

En una transacción y con bloqueos apropiados, registrar el emisor institucional, estación física, localidad canónica, dispositivo y alcance de operaciones concedidas. Generar seis dígitos uniformes con `random_int`, conservando ceros iniciales; guardar únicamente hash resistente a ataques offline, fecha UTC, caducidad propuesta de cinco minutos, contador y estado. El PIN se muestra sólo una vez después del commit, por HTTPS y respuesta sin caché; nunca se guarda en navegador persistente ni logs. Auditar actor, dispositivo, localidad, acción y tiempo sin PIN, hash ni biométricos. Reemisión invalida de forma atómica un PIN pendiente anterior de ese dispositivo; definir límites adicionales por actor y dispositivo. El PIN se suministra al Lector por el Encargado fuera del canal persistente; no se recupera del servidor.

## 3. Canje y sesión administrativa

API autentica primero el dispositivo y su credencial vigente, resuelve su estación del servidor y busca únicamente el PIN administrativo pendiente asociado a ese dispositivo y estación. Con bloqueo transaccional, comprueba propósito, ventana UTC, revocación y estado; revalida usuario activo, puesto 6, permisos y jurisdicción. Un PIN incorrecto incrementa atómicamente el contador y el tercer intento lo bloquea. Añadir límite por dispositivo y origen para impedir repetición mediante múltiples emisiones, sin confiar en cabeceras del cliente; acordar límites concretos y proxy confiable antes de aprobar.

Un canje válido consume una sola vez el PIN y crea en la misma transacción una sesión administrativa de alcance explícito para el dispositivo, credencial, estación, localidad y usuario emisor. Entregar al Lector una credencial de sesión de alta entropía por TLS, una sola vez; persistir sólo su hash y no aceptarla como credencial de marcación. Debe ser revocable desde Portal3 y en cada operación comprobarse en servidor: expiración, revocación, credencial del dispositivo vigente, usuario aún activo en puesto 6, permisos actuales, jurisdicción y trabajador. La duración de sesión y su política de inactividad quedan pendientes de decisión; cinco minutos propuestos corresponden al PIN, no a una duración aprobada de sesión.

Ante respuesta de canje perdida, no permitir que el mismo PIN emita otra sesión ni almacenar en claro un secreto recuperable. El operador revoca la sesión incierta y emite un PIN nuevo. Establecer una referencia de operación idempotente sin permitir recuperar secretos ni repetir una mutación. La revocación de dispositivo o rotación de su credencial debe invalidar sesiones asociadas; una llamada ya autenticada antes de la revocación requiere serialización con la revocación y nueva validación dentro de su transacción.

## 4. Operaciones y datos biométricos

Alcance propuesto: consultar estado, registrar primera huella y actualizar huella. Cada operación verifica sesión, permiso independiente, dispositivo, estación, trabajador activo y pertenencia/identidad canónica vigente; fallar cerrado ante ambigüedad. No se autoriza borrar huellas ni desvincular dispositivo desde Windows. La desvinculación completa permanece en Portal3.

El Lector captura localmente y transmite la plantilla sólo a API por TLS, nunca al navegador de Portal3. API valida formato/tamaño y consistencia de la captura, usa transacción y control de concurrencia sobre el registro biométrico, confirma la nueva plantilla mediante readback y registra auditoría de resultado. La huella anterior permanece utilizable hasta el commit confirmado; ante fallo se revierte. Evitar que una lectura concurrente vea una plantilla parcial. La actualización del catálogo sucede tras confirmar persistencia. Historial visible en Portal3 contiene estado y referencias, nunca huella, hash de plantilla, PIN o secretos. Definir almacenamiento cifrado, acceso, retención y límites del formato antes de implementar.

## 5. Matriz propuesta

| Actor | Consulta de estado/historial | Emitir y revocar PIN/sesión | Primera huella | Actualización | Borrado |
|---|---|---|---|---|---|
| Encargado activo puesto 6, con permisos y localidad | Sí, alcance autorizado | Sí, con permiso específico | Mediante Lector autenticado y sesión con permiso | Igual, con permiso específico | No |
| Dirección autorizada | Sólo consulta | No | No | No | No |
| Lector vinculado | Sólo estado dentro de sesión autorizada | Canjea PIN; no emite | Sólo con sesión válida y ACL vigente | Sólo con sesión válida y ACL vigente | No |
| Sin sesión, permisos o alcance | No | No | No | No | No |

Los permisos efectivos y su asignación concreta deben definirse en la ACL institucional existente, con denegación explícita y precedencia de usuario conservadas. No conceder permisos por la mera pertenencia a Checador.

## 6. Almacenamiento y contratos a conciliar

Reutilizar, tras verificar esquema real y permisos, identidad institucional, localidades/estaciones, identidad y credencial de dispositivo, y las fuentes de auditoría y biometría existentes cuando admitan las garantías. El borrador de vinculación identifica `op_rh_v2_dispositivos`, `op_rh_v2_dispositivo_credenciales` y grants biométricos; esos grants autorizan roster y no equivalen a una sesión administrativa. `op_rh_v2_solicitudes_acceso_temporal` representa solicitudes de asistencia ligadas a un trabajador y su consumo para marcación; no se reutiliza como sesión administrativa sin cambiar su contrato congelado.

Se requieren estructuras persistentes capaces de representar PIN administrativo, sesión revocable vinculada a credencial y operaciones auditadas. **Propuesta para evaluación:** registros separados para PIN, sesión y auditoría/operaciones, o una estructura existente equivalente si se demuestra que conserva propósito, concurrencia, restricciones y trazabilidad. Hay que verificar si la auditoría existente permite estos eventos y si `op_rh_personal_acceso.huella` garantiza unicidad, transacción y readback seguro; sólo entonces se puede determinar el DDL mínimo. No se afirma que existan tablas o columnas nuevas ni se propone ejecutar DDL.

Las operaciones conceptuales de canje, revocación, consulta de estado e inscripción necesitan una interfaz API/Lector coordinada; sus rutas, esquemas, códigos, versionado y estrategias de compatibilidad aún deben acordarse. La interfaz de emisión/revocación de Portal3 será propia y SQL, con CSRF y sin HTTP Portal3→API. La restricción congelada de asistencia no cambia; la dependencia HTTP heredada de Portal3 para ese flujo requiere conciliación aparte si se desea retirarla.

## 7. Pruebas y aprobación

En un entorno aislado: propósitos cruzados; permisos ausentes, revocados o de otra localidad; usuario inactivo o puesto distinto; dispositivo y credencial sustituidos; PIN vencido, tercer fallo, concurrencia de canje y reemisión; pérdida de respuesta; sesión vencida/revocada, permisos cambiados y operación concurrente con revocación; escritura biométrica fallida con conservación de huella anterior; readback, auditoría sin secretos, protección de fuerza bruta, TLS y denegación offline. Ejecutar regresiones completas de asistencia, vinculación y marcaciones. La validación física corresponde al propietario después de la aprobación y la preparación coordinada.

Decisiones pendientes: aprobación de alcance y propietario de escritura biométrica; esquema y protección de plantilla reales; tiempo máximo e inactividad de sesión; permisos y mecanismos de revocación; identificación inequívoca de solicitudes y canal API/Lector; límites de intentos y rate limit; política de historial, retención y recuperación; despliegue y compatibilidad Windows. No iniciar implementación antes de la aprobación expresa.
