# Checador V2 — alta, PIN administrativo y biometría V1.0

**PROPUESTA PARA APROBACIÓN.** Conciliación de `CHECADOR_V2_WORKER_ENROLLMENT_PROPOSAL.md` y `CHECADOR_V2_UNIFIED_PIN_ADMIN_BIOMETRIC_ADDENDUM_PROPOSAL_V1.0.md`. No autoriza implementación, DDL ni despliegue. Este documento define interfaces propuestas, no existentes. Architecture Freeze y `TEMPORARY_ACCESS_API_CONTRACT` permanecen vigentes.

## 1. Alcance y fuentes

Portal3 registra personal mediante SQL institucional y conserva el módulo `/departamento-operativo/recursos-humanos/checador/acceso-temporal` con dos secciones. El PIN de asistencia conserva su contrato, tabla, transporte HTTP existente y comportamiento. Portal3 no usa HTTP a API para emisión, historial o revocación del nuevo PIN administrativo. API es la única escritora de identidades nuevas de dispositivo y plantillas de este flujo; Windows captura huella y se comunica exclusivamente con API. El PIN administrativo jamás autoriza marcación y el PIN de asistencia jamás autoriza operaciones administrativas.

El Encargado institucional es `tb_usuarios.id_puesto=6` y `estatus=0`; no se emplea el nombre de un puesto de `op_rh_personal`. Control de Documentos exige permiso institucional `crear`, sesión válida, CSRF y estación física propia autorizada. El número de colaborador no es identidad externa. No imponer unicidad hasta resolver alcance y duplicados: el 25/11/2026 una lectura local de `bd_admongas` encontró ocho números positivos duplicados entre estaciones, cinco con dos registros activos. No implica resultados de preproducción.

## 2. Identidad de dispositivo

Generalizar `InitialDeviceIdentityProvisioner` y reutilizar el escritor canónico. Para un dispositivo autenticado, resolver estación física activa desde credencial y localidad mediante mapeo unívoco, nunca del JSON. Seleccionar trabajadores activos autorizados de la estación, incluso sin huella. Generar UUIDv4 aleatorio como `referencia_externa_personal` sólo si no existe identidad histórica; conservar una identidad vigente inequívoca; omitir e informar conflictos históricos sin reescribirlos. Nunca derivar referencias de ID o número de colaborador. Serializar mediante el bloqueo canónico del dispositivo y reglas de intervalos del escritor, con transacción, idempotencia por solicitud y readback. Alta y sincronización son transacciones separadas: fallos de sincronización quedan pendientes, sin revertir ni duplicar el alta. No sincronizar en GET.

**Interfaz propuesta, Device Auth existente y TLS:** `POST /api/v2/devices/station/identities/synchronize` con `{"request_id":"<uuid>"}`. Respuesta `200 {"request_id":"<uuid>","created":0,"preserved":0,"conflicts":0}`; reintento idéntico devuelve el resultado confirmado, payload distinto con el mismo ID `409 IDEMPOTENCY_CONFLICT`. Sin datos de otra estación. `401` credencial inválida; `403` dispositivo o jurisdicción inválida; `409 IDENTITY_CONFLICT` si la política de procesamiento total lo requiere; `503` dependencia no disponible. La política exacta de conflictos parciales se decidirá en revisión; no borrar ni cambiar identidades históricas.

## 3. Permisos institucionales propuestos

| Acción | Encargado activo, puesto 6 | Dirección | Lector |
|---|---|---|---|
| Alta RH | `recursos-humanos.crear` y estación física propia autorizada | política RH existente | No |
| Consultar PIN/historial | permiso `acceso-temporal.leer` y localidad | igual | No |
| Emitir PIN administrativo | `acceso-temporal.editar` + `checador.admin_pin.emitir` + localidad | No | No |
| Revocar PIN/sesión | `acceso-temporal.editar` + `checador.admin_pin.revocar` + localidad | No | Cerrar sólo su propia sesión |
| Sincronizar identidad | No llama API | No | dispositivo autenticado, sólo su estación |
| Estado, primera huella, actualización | `checador.biometria.estado`, `.registrar`, `.actualizar` respectivamente, revalidados por API | consulta de historial sin plantilla | sólo con sesión administrativa y permisos vigentes del Encargado |
| Borrar huella/desvincular desde Lector | No | No | No |

Los nombres abreviados de las últimas capacidades se expanden a `checador.biometria.registrar` y `checador.biometria.actualizar`. Deben agregarse a la allowlist y asignarse expresamente mediante el sistema de permisos existente, sin asignaciones automáticas. Denegaciones individuales prevalecen. Toda lectura o escritura comprueba jurisdicción y usuario activo; `id_gas` y localidad canónica se mapean, no se equiparan numéricamente.

## 4. PIN administrativo y sesión

Portal3 emite seis dígitos uniformes mediante `random_int(0,999999)` con ceros iniciales. Guarda `password_hash` resistente a fuerza bruta, nunca PIN en claro; entrega PIN una sola vez tras commit por HTTPS, sin caché, con CSRF y auditoría sin secretos. UTC de BD; cinco minutos `[emitido, expira)`. A lo sumo un PIN pendiente por dispositivo; reemisión revoca el anterior bajo bloqueo. Cada canje fallido incrementa atómicamente su contador; el tercero bloquea. Además, el limitador durable por dispositivo mantiene intentos entre PIN reemitidos: máximo 3 fallos acumulados en 15 minutos, luego bloqueo de 15 minutos; emisión no reinicia contador ni bloqueo. Añadir rate limit de red por IP con proxy confiable y límites operacionales acordados antes de aprobación. Evitar distinguir PIN, usuario o dispositivo inexistentes en respuestas públicas.

API autentica credencial de dispositivo; bajo bloqueo dispositivo→limitador→PIN→sesión, verifica hash, plazo, estado, límite, emisor activo/puesto/permisos y jurisdicción. Un solo canje consume PIN y crea sesión en la misma transacción. Máximo una sesión activa por dispositivo: si ya existe, responder `409 SESSION_ACTIVE`; no invalidarla silenciosamente. API genera secreto de sesión de 32 bytes aleatorios, envía una vez en respuesta TLS y persiste sólo SHA-256; no se acepta como credencial de marcación. En cada operación valida secreto, credencial vinculada, usuario, puesto, permisos vigentes, trabajador, dispositivo y estación bajo control transaccional. Vida absoluta máxima cinco minutos desde creación, inactividad máxima dos minutos; actualizar última actividad sólo en operación autorizada. Revocación, cierre explícito, rotación o revocación de credencial y desvinculación invalidan la sesión. La cancelación se serializa con escrituras para que ninguna confirmación posterior use una sesión revocada.

Estados PIN: `PENDING` → `CONSUMED`, `REVOKED`, `EXPIRED` o `LOCKED`; terminales inmutables. Sesión: `ACTIVE` → `CLOSED`, `REVOKED` o `EXPIRED`. La expiración se verifica por timestamps aunque no haya proceso que actualice estados. Tras ACK perdido de canje no se entrega otra sesión ni se recupera el secreto: revocar la sesión incierta y emitir nuevo PIN. Reintentos de escrituras biométricas usan `request_id` único y comparación semántica; payload diferente devuelve `409`, igual devuelve resultado ya confirmado sin exponer plantilla.

## 5. Interfaces propuestas: Portal3 y API

Todas las mutaciones Portal3 exigen sesión, CSRF y JSON; respuestas sensibles `Cache-Control: no-store`. Ejemplos de IDs ilustrativos; servidor revalida alcance. Rutas relativas bajo `/departamento-operativo/recursos-humanos/checador/acceso-temporal`:

* `POST /admin-pin` entrada `{"workplace_id":1,"device_id":2,"request_id":"<uuid>"}`; `201 {"authorization_id":"<uuid>","pin":"012345","expires_at_utc":"<ISO8601>"}` una sola vez; reintento incierto revoca/reemite, nunca recupera PIN.
* `GET /admin-history?workplace_id=1` devuelve `{"items":[{"authorization_id":"<uuid>","device_id":2,"actor_user_id":10,"state":"PENDING","expires_at_utc":"<ISO8601>","operations":[]}]}` sólo en localidad autorizada y sin PIN, hash, secreto ni plantilla. Paginación acotada será definida en revisión.
* `POST /admin-pin/{authorization_id}/revoke` y `POST /admin-session/{session_id}/revoke`: cuerpo `{"workplace_id":1}`; `200 {"state":"REVOKED"}`. Propias rutas Portal3 y SQL, no llamadas Portal3→API.

API usa Device Auth existente más TLS. Esquema conceptual: el encabezado `X-Administrative-Session: <secreto>` se suma a Device Auth; jamás se coloca en URL ni logs.

* `POST /api/v2/devices/station/admin-sessions/redeem` cuerpo `{"pin":"012345","request_id":"<uuid>"}`; `201 {"session_id":"<uuid>","session_secret":"<64 hex>","expires_at_utc":"<ISO8601>","idle_timeout_seconds":120}`. `401 ADMIN_DENIED` no revela causa, `409 SESSION_ACTIVE`, `429 RATE_LIMITED`, `503 ADMIN_UNAVAILABLE`.
* `POST /api/v2/devices/station/admin-sessions/close` cuerpo `{"request_id":"<uuid>"}`; `200 {"state":"CLOSED"}` y revocación durable.
* `GET /api/v2/devices/station/admin-workers` devuelve `{"workers":[{"external_worker_reference":"<uuid>","worker_id":123,"name":"<nombre>","biometric_status":"ENROLLED|NOT_ENROLLED"}]}` para dispositivo y estación autenticados; sin plantillas. Catálogo paginado y límites concretos se verificarán con el contrato roster existente.
* `GET /api/v2/devices/station/admin-workers/{external_worker_reference}/biometric-status` devuelve `{"biometric_status":"ENROLLED|NOT_ENROLLED","revision":"<opaque-or-null>"}`.
* `POST /api/v2/devices/station/admin-workers/{external_worker_reference}/biometric-enrollments` cuerpo `{"request_id":"<uuid>","mode":"CREATE|REPLACE","expected_revision":"<opaque-or-null>","template_format":"<formato-acordado>","template_base64":"<captura>"}`; `201 {"request_id":"<uuid>","biometric_status":"ENROLLED","revision":"<opaque>"}` sólo tras commit y readback. CREATE requiere inexistencia; REPLACE requiere revisión esperada vigente; incompatibilidades `409`, entrada inválida `422`, autorización `403`, falla SQL `503`. Plantilla nunca aparece en respuesta, auditoría o logs.
* Tras inscripción, Windows refresca el roster existente autorizado, sin modificar el contrato de ese GET.

Los endpoints propuestos son nombres exactos para revisión, no despliegues existentes. Las operaciones no admiten modo offline ni modifican ingesta, DPAPI o cola durable.

## 6. DDL mínimo propuesto, no ejecutar

La tabla congelada de asistencia es específica de trabajador y marcación; no provee sesión ni límites persistentes por dispositivo. Las siguientes estructuras separadas son necesarias para propósito, bloqueo, revocación e idempotencia. Tipos y FK a tablas existentes deben verificarse contra `SHOW CREATE TABLE` del destino; no aplicar hasta aprobación y revisión de índices, InnoDB y permisos. UTC se almacena en `DATETIME(6)`.

```sql
CREATE TABLE op_rh_v2_admin_pin (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 authorization_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 device_id INT NOT NULL, station_id INT NOT NULL, locality_id INT NOT NULL, actor_user_id INT NOT NULL,
 pin_hash VARCHAR(255) NOT NULL, state VARCHAR(16) NOT NULL,
 failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 issued_at_utc DATETIME(6) NOT NULL, expires_at_utc DATETIME(6) NOT NULL,
 consumed_at_utc DATETIME(6) NULL, revoked_at_utc DATETIME(6) NULL,
 pending_device_id INT GENERATED ALWAYS AS (IF(state='PENDING',device_id,NULL)) STORED,
 UNIQUE KEY uk_admin_pin_uuid (authorization_uuid), UNIQUE KEY uk_admin_pin_pending (pending_device_id),
 KEY idx_admin_pin_scope (device_id, station_id, state)
) ENGINE=InnoDB;
CREATE TABLE op_rh_v2_admin_session (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 session_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 pin_id BIGINT UNSIGNED NOT NULL, device_id INT NOT NULL, credential_id INT NOT NULL,
 station_id INT NOT NULL, locality_id INT NOT NULL, actor_user_id INT NOT NULL,
 session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state VARCHAR(16) NOT NULL, created_at_utc DATETIME(6) NOT NULL,
 expires_at_utc DATETIME(6) NOT NULL, last_activity_at_utc DATETIME(6) NOT NULL, ended_at_utc DATETIME(6) NULL,
 active_device_id INT GENERATED ALWAYS AS (IF(state='ACTIVE',device_id,NULL)) STORED,
 UNIQUE KEY uk_admin_session_uuid (session_uuid), UNIQUE KEY uk_admin_session_hash (session_hash),
 UNIQUE KEY uk_admin_session_pin (pin_id), UNIQUE KEY uk_admin_session_device (active_device_id),
 CONSTRAINT fk_admin_session_pin FOREIGN KEY (pin_id) REFERENCES op_rh_v2_admin_pin(id)
) ENGINE=InnoDB;
CREATE TABLE op_rh_v2_admin_pin_limit (
 device_id INT NOT NULL PRIMARY KEY, window_started_at_utc DATETIME(6) NOT NULL,
 failed_attempts INT UNSIGNED NOT NULL DEFAULT 0, blocked_until_utc DATETIME(6) NULL
) ENGINE=InnoDB;
CREATE TABLE op_rh_v2_admin_operation (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 session_id BIGINT UNSIGNED NOT NULL, actor_user_id INT NOT NULL, device_id INT NOT NULL,
 station_id INT NOT NULL, locality_id INT NOT NULL, worker_id INT NULL,
 operation VARCHAR(32) NOT NULL, payload_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 result VARCHAR(24) NOT NULL, result_revision VARCHAR(64) NULL, created_at_utc DATETIME(6) NOT NULL,
 UNIQUE KEY uk_admin_operation_request (request_uuid), KEY idx_admin_operation_scope (station_id, created_at_utc),
 CONSTRAINT fk_admin_operation_session FOREIGN KEY (session_id) REFERENCES op_rh_v2_admin_session(id)
) ENGINE=InnoDB;
```

`payload_digest` must use a server-keyed HMAC for semantic comparison, never a raw template digest; key custody/rotation must be approved and a retry must not expose the digest. A separate durable synchronization idempotency record is required for identity sync unless an existing verified canonical mechanism provides it. No silent mapping of biometric versions onto `op_rh_personal_acceso.huella`: current roster groups by worker and returns a template only when exactly one row exists. Verify transactionality, unique row, concurrency and revision storage; if insufficient, add the minimum versioning/locking structure in a reviewed supplemental DDL before implementation. Retention, foreign keys to canonical entities, encrypted-at-rest requirements and session invalidation of expired `ACTIVE` rows under lock are mandatory design review items.

## 7. Validaciones de integración

Usar bases desechables aisladas: alta puesto 6 propia, sin permiso y estación ajena; duplicados sin restricción nueva; identidades faltantes, existentes, históricas y concurrencia; solicitudes de sync repetidas e incompatibles; PIN incorrecto tres veces, reemisión que no limpia límite, vencimiento y canjes concurrentes; un PIN de cada propósito rechazado por el otro; una sola sesión, expiración absoluta e inactividad, cierre y revocación concurrente; usuario, ACL o dispositivo revocados durante la sesión; acceso ajeno; primera captura y reemplazo simultáneos, rollback, readback y continuidad de plantilla previa; secretos ausentes de logs/respuestas Portal3; DB y red indisponibles; regresiones completas de vinculación, asistencia y cola Windows. Validar físicamente con propietario sólo tras aprobación y despliegue autorizado.

## 8. Prompt de implementación Windows posterior a aprobación

> Implementar en el Lector existente el cliente de las interfaces V1.0 aprobadas: sincronizar identidades del dispositivo por POST idempotente, solicitar PIN administrativo de seis dígitos, canjear mediante Device Auth, mantener secreto de sesión sólo en memoria, bloquear al vencer cinco minutos o dos de inactividad y cerrar explícitamente. Con sesión vigente, listar trabajadores de su estación, consultar estado y capturar primera huella o actualización con revisión esperada y request UUID; enviar plantilla sólo a API por TLS, nunca a Portal3, logs ni almacenamiento persistente. Ante falla conservar la huella previa, informar error y refrescar roster sólo tras confirmación. Denegar offline y no permitir marcaciones con PIN administrativo ni operaciones administrativas con PIN de asistencia. Conservar vinculación, DPAPI, cola durable y protocolos de marcación; pruebas de reintento, concurrencia, caducidad y revocación. No implementar hasta aprobación del contrato y versión de API.

## 9. Recorrido manual futuro

1. Encargado autenticado crea trabajador en su estación; verifica alta y asignación canónica sin usar datos ficticios.
2. Lector sincroniza identidades; comprobar referencia generada, catálogo y ausencia de huella.
3. En PIN y autorizaciones, Encargado emite PIN administrativo y lo introduce en Lector; comprueba sesión y límites.
4. Captura primera huella y confirma estado; actualiza huella, comprueba readback y cierre de sesión.
5. Trabajador realiza marcación física; propietario confirma evento en M01 sin inferir clasificación.
6. Trabajador solicita PIN de asistencia mediante flujo vigente; Encargado lo autoriza y el trabajador lo utiliza una sola vez; comprobar `TEMPORARY_CODE` en M01.

**Bloqueos para aprobación:** verificar esquema y permisos efectivos; política institucional de número de colaborador; proveedor y formato biométrico; fuentes de ACL/API para revalidación; esquema final y claves de cifrado/HMAC; mecanismo de idempotencia de sync; códigos y compatibilidad de Windows. No se han ejecutado escrituras ni DDL en preproducción.
