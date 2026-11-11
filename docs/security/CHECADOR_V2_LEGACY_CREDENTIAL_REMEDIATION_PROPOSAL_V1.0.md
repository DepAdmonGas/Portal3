# Checador V2 — propuesta de remediación de credenciales legacy V1.0

**TASK:** `CHECADOR_V2_LEGACY_CREDENTIAL_SECURITY_REMEDIATION_DESIGN`  
**Fecha:** 2026-10-23, America/Mexico_City  
**Estado:** `DRAFT_LOCAL_PENDING_APPROVAL`  
**Modo:** análisis de solo lectura; este documento es el único entregable nuevo del checkout.  
**Implementación / migraciones / despliegue / publicación:** no autorizados.  
**PR de referencia:** [Portal3 #243](https://github.com/DepAdmonGas/Portal3/pull/243); su revisión y estado remoto no se pudieron verificar.

La recomendación provisional es preparar la **opción B**, con restablecimiento de las credenciales expuestas, autoridad de autenticación en API y retiro obligatorio del mecanismo reversible. La **opción A** queda disponible únicamente si se acredita la ausencia de todos los consumidores, incluidas sesiones existentes y operaciones posteriores al login. La documentación local registra una transición de terminales aún pendiente; no es evidencia operativa actual de producción.

```text
PRODUCTION_LEGACY_AUTH_USAGE: NOT_VERIFIED
IMPLEMENTATION_AUTHORIZED_BY_THIS_DOCUMENT: NO
ARCHITECTURE_FREEZE_MODIFIED: NO
```

## 1. Arquitectura actual y evidencia

### 1.1 Baseline y límites de la revisión

| Recurso | Baseline local revisado |
|---|---|
| Portal3 | `/home/silvinol/Proyectos/Portal3`, HEAD `31eec165faf024f03a877b17242ba274bb0640f7`, rama `feature/checador-v2-portal3-integracion-20261023` |
| API | `/home/silvinol/Proyectos/portal-api-v.1.0.1`, HEAD `9d8cb77fccb0b54828a38f602af258ecefc1d390`, rama `feature/checador-v2-api-integracion-20261023`; worktree limpio al iniciar |
| Cambio ajeno preservado | `app/Views/departamento-operativo/submodulos-index.php`, ya modificado al iniciar; SHA-256 inicial `6ab5b40c02a3dd311c1778109f999eb02aa4df025d45f92a8bc79dacb98f57d5` |
| Architecture Freeze | Copias Portal3/API idénticas: SHA-256 `87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85` |
| Lector | El directorio local llamado `Lector` contiene documentos, sin checkout de código fuente disponible. Dependencias inferidas del servidor y contratos; versión/binarios/flota no verificados |
| PR #243 | `gh` sin autenticación y lectura web sin contenido disponible. No se verificaron head remoto, diff, CI, revisiones ni mergeabilidad; el HEAD local no se equipara al del PR |

Se leyeron los AGENTS de ambos repositorios, las dos skills locales y los documentos obligatorios API: Architecture Freeze, Implementation Status, Master Schedule, Punch Events Ingestion API y Changelog. Como contexto Portal3 se revisó el inventario V8A–V8I consolidado en `docs/status/PORTAL3_CHECADOR_V2_FUNCTIONAL_CLOSURE_MASTER_V8J.md` y la regresión vigente V8M de configuración. No se repitió una auditoría global.

Implementation Status y Master Schedule conservan referencias históricas anteriores a actividades descritas en el changelog. Se usan como evidencia documental fechada, sin reinterpretarlos como autorización nueva. Este diseño respeta la evolución incremental y el retiro selectivo después de comprobar consumidores (Freeze §§34, 41, 42 y 50); no altera nómina, PunchEvents ni contratos físicos V2.

No se abrió ninguna conexión de datos ni se consultaron filas, contraseñas, tokens, logs operativos, Plesk o producción. No se invocaron endpoints de la API, ni siquiera del entorno remoto de preproducción. Las configuraciones locales se inspeccionaron únicamente para nombres de flags no sensibles. No se descifró ninguna credencial.

### 1.2 Cadena Portal3

| Componente / referencia | Comportamiento observado |
|---|---|
| `routes/web.php:740–749` | Configuración y CRUD de perfiles bajo `/departamento-operativo/recursos-humanos/biometricos/configuracion/perfil`; GET datatable y POST create/update/delete con `Route::auth` |
| `app/Core/Route.php:89` | `Route::auth` aplica autenticación y CSRF |
| `app/Controllers/BiometricosController.php:269–333` | Exige ACL `biometricos` por acción; resuelve estación autorizada en alta/lectura y delega actualización/baja por ID al servicio. Actualmente aplica `trim` a la contraseña |
| `app/Services/BiometricosService.php:87–147, 998–1139` | Resuelve estación física autorizada mediante contexto y mapeo único por `numlista`; cifra usuario y contraseña al crear, cifra usuario y contraseña no vacía al editar; baja lógica con `status=0` |
| `app/Models/Operativo/RhLocalidadesPerfil.php:9–26` | Tabla `op_rh_localidades_perfil`; campos `id_estacion`, `usuario`, `password`, `token`, `status`; sin timestamps; no declara `$hidden` para secretos |
| `BiometricosService::datatablePerfil` | Descifra sólo `usuario`. La proyección explícita actual no devuelve ni descifra `password` y no entrega `token` |
| Vista `biometricos/configuracion-perfil.php` y JS `biometricos-config-perfil.actions.init.js:75–80` | Formulario nativo; contraseña vacía al editar; vacío conserva el valor almacenado. Requisitos de composición/longitud comprobados en JS, sin política equivalente completa en el handler revisado |
| `app/Services/ChecadorConfigurationReadService.php:178–220` | Configuración Checador consulta sólo conteo de perfiles activos, con alcance legacy adicional; no necesita contraseñas ni la clave. Departamento sin mapeo físico no se fuerza a estación |
| `tests/checador_v8m_configuration_regression.php:130–149` | Comprueba proyección sin contraseña, formulario vacío, ACL/scope y separación de configuración V2 |

La detección de duplicados del alta legacy compara **usuario cifrado + contraseña cifrada + status activo**, sin unicidad del login ni filtro por estación. La edición no hace esa comprobación. No se puede asumir que el nombre de usuario identifique un perfil único.

### 1.3 Cadena API y consumidores

| Componente / referencia API | Comportamiento observado |
|---|---|
| `modelo/Encriptar.php:6–28` | Transformación determinista reversible: suma/resta de bytes con clave fija repetida y Base64. No es un esquema moderno de cifrado autenticado ni un hash de contraseña |
| `modelo/Acceso.php:9–70` | `AccesoEmpresa` cifra ambos valores recibidos y compara por igualdad SQL con perfiles activos. No necesita descifrar la contraseña para autenticar |
| `Acceso.php:26–35` | Une `op_rh_localidades.id = op_rh_localidades_perfil.id_estacion`; `LIMIT 1` oculta posibles coincidencias múltiples |
| `Acceso.php:22,43–55` | Genera token aleatorio con prefijo literal `JWT `; es opaco, sin firma/claims JWT. Lo guarda en claro y sobrescribe el token de **todos los perfiles activos de la estación**, no sólo el autenticado |
| `modelo/Empresa.php:6–44` | Valida token por igualdad en `op_rh_localidades_perfil.token`; no comprueba `status`, expiración ni revocación. Resuelve estación mediante otra consulta. SQL construido por concatenación |
| `modelo/Empresa.php:47–95` | Comprueba trabajador/estación, pero permite una excepción amplia cuando el trabajador pertenece a localidad 9. No equivale al permiso explícito Palo Solo–Autolavado de V2 |
| `modelo/Personal.php:48,97,200,281,352` | Cifra IDs de personal en listados y descifra `idpersonal` para editar PIN/huella |
| `modelo/Acceso.php:73–101` | `AccesoPersonal` valida el token, descifra `idpersonal`, comprueba pertenencia y ejecuta asistencia legacy |
| `index.php:156–175,177–338` | Protege las seis rutas legacy con gate de entorno. Autenticación en `acceso-empresa`; sus tokens se consumen en `personal`, `editar-pin`, `editar-huella`, `acceso-personal`, `personal-asistencia` |
| Pruebas API | `v2_sec_b_acceso_empresa_transport_tests.php`, `v2_preprod_legacy_gate_configuration_tests.php`, `v2_station_alignment_api_tests.php`, `v2_sec_d1_method_content_payload_tests.php` |

Los listados legacy incluyen PIN/huella en su propio dominio; no son la contraseña del perfil. Su custodia y protocolo de enrolamiento necesitan una revisión específica si se mantienen. Este diseño no autoriza migrar esos datos ni trasladarlos a PunchEvent.

**Dependencia crítica de identidad:** Portal3 escribe `id_estacion` como `tb_estaciones.id`, mientras `AccesoEmpresa` lo interpreta como `op_rh_localidades.id`. Es una discrepancia verificable en código; no se evaluó qué filas reales afecta. No corregirla con igualdad numérica, un backfill supuesto o una conversión masiva. Cada perfil necesita un vínculo autoritativo y único estación física → `numlista` → localidad canónica aprobado antes de activación; ausente/ambiguo implica bloqueo del perfil y resolución administrativa.

### 1.4 Autenticaciones V2 independientes

- Dispositivo: `modelo/v2/DispositivoV2.php::autenticarCredencial`; `X-Device-Id`, `X-Credential-Key`, Bearer secreto y `password_verify` sobre `secret_hash`, estado, vigencia y revocación. No usa `Encriptar` ni el perfil legacy.
- Persona Portal3: `ChecadorApiJwtService` emite JWT HS256 de 300 segundos con clave dedicada `CHECADOR_API_JWT_SECRET`; API valida con su `JWT_SECRET`, `iss=portal3_auth`, `type=access`, `sub`, `exp`, usuario activo y ACL/scope frescos. No usa el token opaco legacy.
- Sesión web Portal3: `AuthenticationService`, `PasswordService`, `TokenService` y `JWTService`. Es un dominio distinto. `PasswordService::verify` admite texto plano legacy para usuarios web; **no reutilizar ese fallback** como validador de los nuevos perfiles.
- Ingesta, solicitudes/validación de acceso temporal, programación y consultas V2 conservarán sus fronteras y contratos actuales. Portal3 no empleará Device Auth; Lector no recibirá JWT de supervisor ni claves de firma/HMAC.

## 2. Riesgos y alcance de exposición

| Riesgo | Implicación / tratamiento requerido |
|---|---|
| Clave histórica publicada y compartida | Tratarla como potencialmente comprometida. La confidencialidad del ciphertext no protege las contraseñas; no basta mover la clave a `.env` o sustituirla por otra |
| Cifrado determinista de contraseñas | Sustituir por hash robusto con salt individual. No hashear el ciphertext como si fuera la contraseña; no realizar descifrado masivo |
| Sesiones persistentes en claro | Robo/reutilización del bearer; un cambio de contraseña o baja lógica hoy no asegura invalidación de token. La revocación debe ser explícita y server-side |
| Token por estación, `LIMIT 1`, usuarios no únicos | Identidad ambigua, sesiones compartidas y colisiones. Prohibir selección arbitraria y actualización masiva por estación |
| Confusión estación/localidad y excepción localidad 9 | Riesgo de alcance incorrecto. Requiere mapeo autoritativo y permisos explícitos, sin heredar la excepción amplia al mecanismo nuevo |
| GET de autenticación habilitable | Contraseñas en URL, historial, proxies y telemetría. POST solo evita esa vía; no corrige almacenamiento reversible |
| SQL por concatenación | Parametrizar autenticación, sesión y operaciones afectadas; no considerar Base64 u ofuscación una defensa SQL |
| Validación de contraseña sólo en JS / trim asimétrico | Peticiones directas pueden eludir política; Portal3 recorta y API conserva espacios. Definir semántica única antes de cambiar formatos |
| Modelo serializable | La proyección actual es segura para la contraseña, pero un futuro `toArray()` podría filtrarla. Ocultar campos sensibles y mantener allowlist de respuestas |

### 2.1 Revisión de exposición sin reproducir secretos

Se comparó en memoria el literal histórico de ambas implementaciones: coincide. Se buscó su ocurrencia exacta sin imprimir el literal, fragmentos, hash identificador de la clave o contraseñas:

| Superficie local inspeccionada | Resultado |
|---|---|
| Archivos versionados y no ignorados de Portal3 | Coincidencia sólo en `app/Services/BiometricosService.php` |
| Archivos versionados y no ignorados de API | Coincidencia sólo en `modelo/Encriptar.php` |
| Objetos Git alcanzables por refs locales | Portal3: 2 blobs de ese servicio; API: 1 blob de Encriptar. 0 mensajes de commit coincidentes |
| Documentación/auditorías versionadas, tests y artefactos en ese conjunto | Sin coincidencias adicionales exactas |
| ZIP locales del conjunto y ZIP de contrato API codificado en Base64 | Sin coincidencias en miembros descomprimidos revisados; sin errores de lectura de ZIP |
| CI remoto, artefactos externos, PR/comentarios, salidas compartidas fuera del checkout, registros de auditoría/servidor reales, backups | `NOT_VERIFIED`; no consultados por restricción de la tarea y/o falta de acceso |

El resultado negativo se limita a coincidencias literales y objetos locales alcanzables; no cubre representaciones partidas/codificadas, objetos inaccesibles, archivos ignorados, forks o copias externas. No permite afirmar ausencia histórica de exposición. Un custodio autorizado deberá revisar esas superficies con búsqueda redactada y emitir sólo ubicación/categoría/alcance, sin adjuntar contenidos sensibles. Si existen registros con credenciales, restringir acceso, preservar evidencia bajo custodia y aplicar retención/saneamiento autorizado; no copiarla a esta propuesta. La rotación es necesaria aunque se eliminen copias del código. Una eventual limpieza del historial exige autorización independiente y no sustituye rotación/revocación.

## 3. Activación vigente y arquitectura objetivo

### 3.1 Gate existente: configuración de despliegue efectiva

Autoridad: `index.php::checador_is_legacy_route_allowed()`. La función consulta `getenv`, por lo que importa el entorno **del proceso PHP/FPM que atiende cada host**, no sólo el archivo de ejemplo, el shell o la URL.

| Configuración | Resultado del código actual |
|---|---|
| `CHECADOR_DEPLOYMENT_PROFILE` ausente, vacío o desconocido | Bloquea legacy |
| `preproduction`, `v2_preproduction_isolated` | Bloquea incluso con `LEGACY_ROUTES_ENABLED=true` |
| `production`, `historical_production_dual` | Permite base, salvo restricción o conflicto |
| `development`/`dev`, `test`/`testing` | Permite base, salvo restricción o conflicto |
| `APP_ENV` presente de categoría distinta, desconocida o preproducción | Bloquea; por sí solo nunca habilita |
| `LEGACY_ROUTES_ENABLED=false/0/no/off` | Bloquea las seis rutas |
| `LEGACY_ROUTES_ENABLED=true/1/yes/on`, ausente o vacío | No habilita por sí solo; conserva el resultado del perfil |
| `LEGACY_ROUTES_ENABLED` no vacío inválido | Bloquea |
| Gate bloqueado | HTTP 503, `Cache-Control: no-store`; antes del handler y de SQL legacy |

Después de ese gate, `LEGACY_ACCESO_EMPRESA_GET_ENABLED=1` o `true` (sin distinción de mayúsculas para `true`) permite GET de login. Ausente/deshabilitado devuelve 405 con `Allow: POST`; no bloquea POST ni revoca tokens. POST lee JSON/form body y no cae a query string. El flag GET no puede vencer al gate general. Se reconocen rutas sin prefijo y prefijos históricos `portal-api-v.1.0.1` y `portal-api-v.2.0`.

En el proceso CLI observado no estaban exportados esos cuatro flags; en `.env` local Portal3 sólo se encontró `APP_ENV=dev` entre esos nombres. No se encontró `.env` en la raíz API ni asignación de esos flags en los `.htaccess` raíz inspeccionados. Esto **no determina** la configuración efectiva de FPM, otros virtual hosts ni producción. Portal3 no contiene un gate específico equivalente alrededor del CRUD de perfiles revisado: depende de autenticación, ACL y alcance.

`docs/deployment/CHECADOR_V2_PREPRODUCTION_TARGET_DEFINITION_V1.0.md:59–102` identifica el dominio V2 oficial como preproducción aislada y prohíbe mezclar JWT de supervisor/Device Auth. No se verificó remotamente ni debe confundirse con el despliegue histórico. `docs/status/CHECADOR_V2_IMPLEMENTATION_STATUS.md:108` y el documento de despliegue describen siete terminales remotas aún pendientes de transición POST: indicio documental que impide asumir opción A.

### 3.2 Evidencia requerida para determinar uso real

En una actividad operativa **posterior y autorizada**, los responsables API/Lector/Infra deberán entregar:

1. Inventario completo de consumidores y terminales, propietario, versión, estación/localidad, URL/alias, método GET/POST, credencial/perfil asignado y operaciones que utilizan token/`idpersonal`. Incluir programas antiguos, tareas, integraciones, estaciones fuera de línea y equipos de respaldo.
2. Atestación redactada de los tres flags del gate y el flag GET desde cada pool/proceso efectivo y cada despliegue; no un volcado de ambiente.
3. Contadores agregados por ruta, método, consumidor y resultado, último uso y longitud de ventana. No URLs con query, cuerpos, nombres de login, Authorization ni tokens. Inventario de sesiones aún válidas, sin extraer valores bearer.
4. Confirmación de fuente completa: balanceadores/hosts/alias, tráfico con tokens ya emitidos y operaciones sin login reciente. Cero logins por sí solo no demuestra desuso.
5. Observación propuesta de al menos 30 días y del ciclo de negocio/offline máximo, el que sea mayor; aprobación del propietario sobre representatividad, exclusiones y lectores inactivos. Un equipo ausente debe quedar retirado formalmente y bloqueado al volver, nunca supuesto migrado.
6. Ensayo de retiro en un entorno aislado con consumidores representativos y firma de cada responsable. Reconciliar capturas pendientes y efecto en asistencia legacy antes de apagar rutas que escriben resultados oficiales.

Hasta disponer de esa evidencia: `PRODUCTION_LEGACY_AUTH_USAGE: NOT_VERIFIED`; no deshabilitar producción basándose en el checkout.

### 3.3 Objetivo y fronteras

```text
Usuario autorizado → Portal3 MVC + sesión + CSRF + ACL biometricos + scope
                             ↓ JWT humano dedicado, sólo server-to-server
                  API: administración de perfiles/credenciales
                             ↓
                   Hashes + estados + sesiones revocables
                             ↑
Consumidor legacy actualizado → TLS + POST acceso-empresa + credencial nueva
                             ↓ token opaco de sesión
             Rutas legacy autorizadas por perfil, acción y localidad

Lector Device Auth → API V2                  [frontera existente independiente]
Portal3 JWT humano → API V2 + ACL/scope        [frontera existente independiente]
```

API será la única autoridad para generar/verificar hashes y cambiar versión/estado de credenciales; Portal3 administrará mediante su MVC y un contrato de API propuesto separado de los contratos congelados V2. El navegador sólo hablará con Portal3. No habrá cifrado reversible de contraseñas nuevas, cifrado en JS, doble escritura de contraseñas ni fallback desde hash fallido hacia legacy.

## 4. Cambios previstos en Portal3

Sólo después de autorización de implementación:

- Conservar rutas, controlador, servicio, vista, Bootstrap/Alpine y componentes de estación existentes. Sustituir operaciones directas de credencial del servicio por llamadas server-to-server a la autoridad API, sin un segundo almacén local de hashes.
- Mantener `biometricos.leer/crear/editar/eliminar` y scope por recurso en cada handler, también para solicitudes directas. La API repetirá la autorización. El permiso de menú Checador no concede administración de perfiles; puestos 3/14 no reciben alcance global. Puesto 6 conserva su estación; puesto 13 sólo centros activos, soportados y autorizados.
- Listar únicamente ID, alias de login no secreto, estación/localidad, estado de migración, versión y fechas no sensibles. Nunca serializar contraseña, ciphertext, hash, token de sesión o token de restablecimiento. Para perfiles aún sin alias moderno mostrar ID/estado y exigir resolución administrativa; el listado no necesitará descifrar `usuario`.
- Contraseña siempre vacía en DOM/modelo al abrir/editar/cerrar; vacío exacto en edición ordinaria significa conservar, nunca activar/migrar/restablecer por sí solo. Restablecimiento es una acción explícita, exige nueva contraseña y reautenticación reciente del administrador; no recuperar ni mostrar la anterior.
- Crear/editar/restablecer con validación server-side compartida por contrato. No recortar, normalizar o transformar contraseñas nuevas; preservar sus bytes. Separar el tratamiento del username y los errores genéricos de autenticación de los errores administrativos autorizados.
- Aplicar ocultamiento defensivo de `password`, `token` y futuros hashes en el modelo, manteniendo proyecciones explícitas. Revisar errores, DataTables, exports, notificaciones y `createAction` para que no reenvíen payloads sensibles.
- Timeout o ACK perdido → estado pendiente/desconocido y consulta de operación por UUID. No mostrar éxito ni reintentar con un mecanismo anterior. Nunca persistir la contraseña en sesión, almacenamiento web, cola o logs.
- `ChecadorConfigurationReadService` seguirá consultando sólo metadatos y separará indisponibilidad de cero perfiles. No se modificará ingesta, programación, evaluación ni acceso temporal V2.

## 5. Cambios previstos en API y contrato candidato

### 5.1 Contraseñas e identidad

**Política propuesta para credenciales nuevas:** `password_hash(..., PASSWORD_ARGON2ID)`, salt automático, memoria mínima 19 MiB, 2 iteraciones y paralelismo 1; medir coste/concurrencia en el runtime de destino antes de aprobar parámetros finales. Si Argon2id no existe, bloquear activación hasta aprobar explícitamente bcrypt con coste medido ≥12 y límite de 72 bytes; sin degradación automática. Usar `password_verify` y `password_needs_rehash`, nunca igualdad de hashes ni un algoritmo rápido para contraseñas. El hash se acepta sólo desde el servidor, jamás desde un campo del cliente. Véanse [PHP password_hash](https://www.php.net/manual/en/function.password-hash.php) y [OWASP Password Storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html).

Política de producto propuesta, pendiente de aprobación conjunta: mínimo 15 caracteres, máximo 128 caracteres/512 bytes con Argon2id, rechazo de contraseñas comunes/comprometidas mediante fuente local aprobada, sin consultas a terceros con el secreto ni reglas de composición obligatorias. Rechazar valores vacíos, tipos no string, bytes NUL, codificación inválida y tamaños excesivos; no truncar. Bcrypt requiere un máximo de bytes distinto expresamente comunicado. No aplicar esa política retroactivamente durante la comprobación del secreto viejo; sólo a altas/reset/cambios. La discrepancia histórica de `trim` se resuelve con restablecimiento, sin probar múltiples variantes.

El login moderno será un alias no secreto, globalmente único entre perfiles activos (necesario para conservar el body histórico sin estación obligatoria), preferentemente nuevo por consumidor. Propuesta de sintaxis: ASCII `[a-z0-9._-]`, 3–128 bytes, comparación canónica en minúsculas. Prohibir aliases ambiguos con registros legacy aún aceptados; verificar colisiones de manera interna, sin revelar credenciales. No conservar la unicidad usuario+contraseña. API vinculará el perfil a una localidad canónica validada; el cliente no podrá cambiar ese vínculo mediante parámetros.

### 5.2 Compatibilidad de transporte y sesiones

Conservar como contrato de compatibilidad `POST /acceso-empresa`, JSON o form body con `usuario` y `password`, y el envelope `resultado` utilizado por clientes existentes. Resolver un único perfil explícito; si es moderno, verificar **sólo** su hash. Sin coincidencia única, denegar. No reutilizar `LIMIT 1` ni adivinar formato por prefijo del valor almacenado. GET se retirará al completar SEC-B; no se habilitará en preproducción aislada para probar este diseño.

El valor de `resultado.token` seguirá siendo opaco y podrá conservar el prefijo `JWT ` durante compatibilidad; no implica que sea un JWT. Generar material aleatorio de al menos 256 bits con CSPRNG. Guardar únicamente digest SHA-256 del token aleatorio de alta entropía en una tabla de sesiones, nunca la contraseña ni el token en claro. Este digest es admisible para tokens aleatorios; no sustituye Argon2id para contraseñas. No reescribir tokens por estación.

Sesión nueva: perfil concreto, versión de credencial, localidad, permisos de operación aprobados, emisión, expiración absoluta propuesta de 15 minutos y revocación. No habrá refresh token en V1 de esta propuesta; el consumidor reautentica por POST. Cada petición valida sesión, expiración, perfil activo, versión vigente, permiso de operación y pertenencia de trabajador/recurso. Cambiar contraseña, desactivar perfil o activar una versión invalida sus sesiones previas en la misma transacción. Fallos del almacén de sesiones/ACL/mapeo deniegan con 503; no se permite fallback a la columna `token`. La expiración y revocación se aplican en servidor; véase [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).

### 5.3 Administración desde Portal3: endpoints propuestos, inexistentes hoy

El namespace candidato es `/api/legacy-profile-auth/v1`, separado de `/api/v2`; requiere aprobación de API, Portal3 y responsables de consumidores. Mantener routing procedural y servicios actuales del API, sin agregar frameworks. Todas las acciones administrativas usan JWT humano dedicado y vuelven a comprobar usuario activo, módulo `biometricos`, acción y scope canónico.

| Operación candidata | Entrada permitida / ACL | Salida no sensible |
|---|---|---|
| `GET /profiles?workplace_id=...` | `leer`, localidad autorizada; paginación acotada | Metadatos, estado, versión y `has_password`; sin secretos |
| `POST /profiles` | `crear`, `operation_uuid`, `workplace_id`, `login`, `password` | ID/versión y estado preparado; sin contraseña |
| `PATCH /profiles/{id}` | `editar`, `operation_uuid`, `expected_version`, cambios permitidos de metadatos | Metadatos/versionado; no acepta cambio de localidad por este contrato |
| `POST /profiles/{id}/credential-reset` | `editar`, reautenticación reciente, `operation_uuid`, `expected_version`, `new_login`, `new_password` | Nueva versión **preparada**, no activación automática |
| `POST /profiles/{id}/credential-activation` | `editar`, reautenticación reciente, UUID/versión, referencia de preparación aprobada | Versión activa, revocación aplicada, sin token de consumidor |
| `POST /profiles/{id}/deactivation` | `eliminar`, reautenticación reciente, UUID/versión | Baja y sesiones revocadas |
| `GET /operations/{operation_uuid}` | Mismo actor autorizado + scope de la operación | Estado/ID/versión, sin payload original |

Estos nombres son propuesta, no endpoints disponibles ni contratos congelados. La reautenticación reciente requiere prueba server-side de Portal3 verificable por API, ligada a actor, acción, recurso, UUID y caducidad máxima propuesta de 5 minutos, mediante firma de propósito separado; un booleano/fecha enviados por el navegador o el JWT emitido en ese momento no demuestran reautenticación. Debe aprobarse el contrato de esa prueba antes de activar reset/baja, sin cambiar los JWT V2 existentes ni compartir la nueva clave con Lector.

Sólo se aceptan campos de allowlist, límites de cuerpo y tipos estrictos. `expected_version` aplica control optimista; cambios de credencial/activación/revocación son transaccionales y serializados por perfil. Idempotencia por actor+UUID+operación: guardar estado y resultado no sensible; para detectar payload divergente usar un HMAC de petición con clave operativa independiente y retención corta, nunca un hash rápido público de una contraseña. No almacenar el body. ACK perdido → consultar operación; nueva clave HMAC no reactiva ninguna credencial.

Errores candidatos: 400 entrada inválida; 401 autenticación genérica; 403 ACL/scope; 404 recurso no visible; 409 versión/alias/UUID ambiguo o conflictivo; 429 con `Retry-After`; 503 dependencia no disponible. Respuestas sensibles, errores y login con `Cache-Control: no-store`. No reflejar inputs, SQL ni excepciones. Rate limiting por cuenta pseudonimizada y origen confiable con almacenamiento compartido; si falla, responder 503, sin acceso ilimitado. Umbrales y capacidad requieren aprobación y prueba en el destino.

### 5.4 Eliminación de dependencia de la clave para IDs

La remediación de contraseñas y el retiro del codec de `idpersonal` son dos dimensiones de migración distintas. Tras restablecer el password puede seguir existiendo dependencia de la clave para IDs; ese estado no permite declarar el cierre definitivo.

Contrato candidato: referencias opacas aleatorias de personal, persistidas con perfil/localidad/trabajador/versión de codec, y verificadas server-side contra alcance en cada operación. No son credenciales ni autorización por sí mismas. Confirmar si los clientes sólo transportan el string o lo descifran localmente antes de sustituir el campo; sin esa evidencia no cambiar `idpersonal`. No aceptar silenciosamente tanto ciphertext como referencia opaca intentando decodificadores sucesivos. La versión de codec debe proceder de un registro/cohorte confiable del servidor; un header libre no habilita el codec viejo. Los contratos DeviceWorkerIdentity/PunchEvent V2 permanecen intactos.

## 6. Impacto en clientes, lectores y estaciones

| Consumidor | Impacto / gate |
|---|---|
| Cliente con GET acceso-empresa | Debe actualizar a POST antes del corte; comprobar que nunca construye URLs con contraseña, tampoco en error/reintento |
| Cliente con POST ya implantado | El body y envelope pueden mantenerse. Necesita la credencial nueva y soportar 401 por expiración/revocación, 429/backoff y 503 sin pérdida de capturas |
| `personal`, `editar-pin`, `editar-huella`, `acceso-personal`, `personal-asistencia` | Necesitan validador común de sesiones, permisos mínimos por operación y scope; migración del codec de IDs comprobada por cliente |
| Estación con varios perfiles/terminales | No depender de que iniciar sesión en uno cambie el token de todos. Inventariar sesiones compartidas y separar identidad por consumidor |
| Lector V2 | Device Auth e ingesta no cambian. Un mismo ejecutable puede conservar tareas legacy de catálogo/enrolamiento/asistencia: Device Auth funcional no prueba independencia completa |
| Portal3 V2 | JWT humano dedicado y ACL existentes; las lecturas de configuración sólo necesitan metadatos. No derivar de un perfil legacy la identidad del usuario Portal3 |
| Autolavado | No usar estación física inactiva 11 ni igualdad con localidad. Excepción Palo Solo localidad 2 / Autolavado localidad 9 sólo mediante autorización explícita vigente |
| Tareas, estaciones offline y clientes desconocidos | Identificar propietario/versiones/retención de cachés. Si no se actualizan antes del plazo, retirar formalmente o bloquear; no dejar una excepción permanente |

Los secretos nuevos de un cliente se aprovisionan mediante canal seguro autorizado y almacén protegido del sistema del cliente. No enviarlos por correo/chat, respuestas JSON de administración, argumentos CLI, archivo de configuración publicado, logs o artefactos. Si la UI requiere que un operador introduzca el password, se escribe como entrada transitoria y se limpia; el servidor nunca lo devuelve. No se ha realizado aprovisionamiento.

## 7. Cambios de esquema previstos, sin DDL ejecutado

Para B se requiere persistencia aditiva. La forma final requiere inspección posterior autorizada de **metadatos de esquema**, motor, tipos, longitudes, collations, índices, FK y topología de bases; no se ha inspeccionado ninguna fila ni se propone SQL ejecutable aquí. No asumir que Portal3 y API comparten físicamente una base porque usen nombres de tabla iguales.

| Entidad candidata, bajo propiedad API | Campos/invariantes mínimos |
|---|---|
| Credencial de perfil, sidecar de `op_rh_localidades_perfil` | ID de perfil único, localidad canónica validada, login canónico único, hash `VARCHAR(255)` o capacidad equivalente, algoritmo/parámetros, versión activa/preparada, estado, cambio de contraseña, reset requerido, fin de compatibilidad |
| Versiones preparadas de credencial | Perfil, versión monotónica, alias/hash preparado, estado y caducidad. Preparar no autoriza login operacional; acceso de prueba restringido a entorno/cohorte segura. Una sola activa, sin downgrade |
| Sesiones de perfil | Identificador/digest único de token, perfil, versión de credencial, localidad, permisos, emisión/expiración/revocación. Sin bearer en claro; índices por digest/perfil/expiración |
| Registro de transición/consumidores | Perfil/cohorte, identidad de consumidor verificada cuando aplique, permisos, codec, deadline, readiness y responsable. No aceptar `client_id` autodeclarado como prueba |
| Referencias opacas de personal, si el cliente legacy continúa | Perfil/localidad/trabajador/codec, referencia única, vigencia/revocación; nunca reemplaza autorización de trabajador |
| Operaciones idempotentes/auditoría | Reutilizar almacenamiento existente sólo si satisface scope, concurrencia y ausencia de secretos; UUID, actor, acción, versión, resultado, tiempo y referencia de evidencia |

Los nombres físicos no están congelados. La migración aditiva no sobrescribirá `password` con un hash incompatible ni reinterpretará `usuario` en sitio. Las columnas reversibles antiguas se anulan/purgan únicamente tras cortar su autoridad y comprobar clientes, con autorización específica; conservar sólo el contenedor/ID si otras relaciones lo necesitan. No mezclar con tablas de marcaciones, nómina, PIN, huellas o credenciales V2. A no requiere tablas de hashes si el servicio queda totalmente retirado; cualquier limpieza de columnas/datos sigue necesitando autorización propia.

## 8. Compatibilidad con credenciales existentes

Estados candidatos de autoridad por perfil:

| Estado | Verificación admitida | Transición |
|---|---|---|
| `LEGACY_TEMPORARY` | Comparación legacy sólo con perfil inequívoco, cohorte autorizada y antes de deadline | Preparar reset o retirar |
| `RESET_PREPARED` | Sigue vigente únicamente la autoridad anterior dentro de su plazo; hash nuevo inactivo | Activar tras readiness completa |
| `HASH_ONLY` | Sólo hash de versión activa; nunca contraseña/ciphertext/token antiguo | Rehash, cambio seguro o baja |
| `RESET_REQUIRED` | Sin login operacional | Recuperación administrativa autenticada |
| `DISABLED` | Denegación de credenciales y sesiones | Reactivación exige nueva autorización/reset |

La aceptación legacy estará deshabilitada por defecto. Autorizarla requiere registro explícito, fecha de inicio/fin UTC, propietario y controles comprobados; el plazo global propuesto es como máximo 30 días desde el inicio aprobado de transición, con plazo menor por cohorte. No arrancar la transición sin cronograma viable para todas las estaciones. Sin fecha válida, al vencer, o ante error de configuración → denegar. No renovar automáticamente ni conservar fallback para perfiles `HASH_ONLY`.

Si se necesita limitar además por terminal, usar identidad de consumidor comprobable (por ejemplo certificado cliente con mTLS y vínculo server-side, sujeto a viabilidad y aprobación de Infra/Lector). IP/NAT, username, un header o `client_id` solos no prueban identidad del equipo. Una terminal incapaz de satisfacer el control no puede contar como cohorte segura; requiere actualización previa o ventana de mantenimiento aprobada. La protección temporal no elimina la exposición de las contraseñas viejas.

No migrar automáticamente todos los passwords descifrados ni considerar un login legacy prueba suficiente de legitimidad después de publicación de la clave. El login correcto podrá servir para telemetría controlada, pero no elimina la obligación de reset. Perfiles con duplicidad, mapeo dudoso, propietario desconocido, baja, ausencia de evidencia de uso o incumplimiento del plazo quedan `RESET_REQUIRED`/`DISABLED`, sin inventar un mapeo o contraseña.

## 9. Estrategias de migración

### Opción A — ausencia productiva demostrada

**Factible técnicamente; no habilitada por la evidencia actual.**

1. Completar y firmar la evidencia de §3.2, incluyendo las cinco operaciones consumidoras de token y los IDs persistidos/cacheados. Confirmar que retirar autenticación no interrumpe asistencia legacy oficial ni enrolamiento.
2. Preparar controles server-side. Con aprobación de despliegue, establecer `LEGACY_ROUTES_ENABLED=false` en cada proceso/host histórico y verificar bloqueo de las seis rutas y todos los alias; mantener preproducción V2 bloqueada como hoy. Bloquear también el CRUD de credenciales Portal3 para evitar nuevas dependencias. No cambiar el perfil a preproducción sobre producción como atajo.
3. Con autorización específica, invalidar sesiones/perfiles retirados, sanear credenciales reversibles y retirar código muerto/call sites de cifrado. No basta cerrar `/acceso-empresa` dejando tokens vigentes aceptados por otras rutas.
4. Si no queda un consumidor de perfil, no crear un login sustituto sin necesidad. Si subsiste una necesidad legítima, usar el mecanismo seguro de B o Device Auth V2 según su frontera aprobada; no redirigir automáticamente rutas legacy de asistencia a ingesta V2.
5. Verificar lectores/estaciones representativos y retirada de equipos ausentes; ninguna ruta o tarea depende del codec. Sólo entonces eliminar la clave de runtime y gestionar copias históricas bajo custodia.

### Opción B — consumidores existentes o uso no acreditado

**Factible con contrato/esquema, mapeo y readiness de consumidores aprobados; recomendada para preparar trabajo.**

1. Inventariar consumidor→perfil→estación/localidad, duplicados y dependencia del codec sin recuperar passwords. Elegir piloto con propietario; no tocar producción en esta fase.
2. Diseñar y probar persistencia aditiva y API con lectura explícita de versiones, hashes/sesiones nuevas y compatibilidad acotada. API primero: toda instancia detrás del balanceador debe reconocer el esquema antes de habilitar escrituras nuevas. No activar perfiles con nodos de API antiguos en servicio.
3. Desplegar después el adaptador Portal3 que deja de cifrar y llama a API. Hasta entonces no escribir formatos nuevos con el antiguo CRUD. Bloquear escrituras por proceso antiguo sobre perfiles migrados mediante control efectivo de servidor, no sólo UI.
4. Actualizar consumidores a POST y a expiración/reautenticación, proteger almacenamiento local, retirar credenciales de URLs/logs y probar contratos en entorno desechable aislado. La preproducción V2 sigue bloqueando legacy; usar laboratorio separado con perfil `test` y datos sintéticos para el flujo legacy.
5. Preparar contraseña/alias nuevos por perfil, sin activar. Registrar versión y readiness de **todos** sus consumidores; probar el recorrido con credenciales sintéticas o canal de ensayo separado, sin admitir el hash preparado en el login operacional.
6. Activar por perfil/cohorte en ventana coordinada: cambiar autoridad a `HASH_ONLY`, incrementar versión, revocar sesiones previas y desautorizar ciphertext en una transacción. Actualizar consumidores, reautenticar y comprobar catálogo, acciones necesarias y asistencia con pruebas aprobadas. La cola durable V2 no implica cola durable legacy; acordar mantenimiento/contingencia antes de cualquier corte de captura legacy.
7. Migrar codec de IDs por cohortes comprobadas, invalidar caches/referencias viejas y eliminar dependencia de la clave. No posponerlo indefinidamente después de hash-only.
8. Llegado el plazo, apagar comparador legacy/GET y rechazar perfiles no migrados. Si una estación sigue sin preparación, resolver antes de iniciar la ventana global o retirarla formalmente; no ampliar silenciosamente el plazo. Retirar definitivamente cifrado, clave, columna de tokens en claro y ramas muertas una vez cumplidos los gates de cierre.

No se garantiza continuidad mediante fallback vulnerable. La ausencia de un mecanismo seguro de actualización/contingencia en un consumidor es un bloqueo de despliegue, que debe resolverse antes del corte. Este documento no convierte una dependencia desconocida en compatibilidad verificada.

## 10. Rotación, revocación y recuperación de cuentas

- Tratar todos los passwords protegidos por la clave histórica como potencialmente comprometidos. La población exacta se obtendrá posteriormente por metadatos/propietario; no se requiere mostrar ni descifrar valores.
- Restablecer todos los perfiles activos que continúen y desactivar los retirados. Credenciales nuevas por consumidor cuando sea posible; sin reutilizar la anterior. Verificación interna de no reutilización durante compatibilidad, sin exportar valores ni preservar un nuevo ciphertext. No usar sólo rehash oportunista como cierre de incidente.
- Revocar sesiones legacy del perfil al activar, cambiar secreto o desactivar; el validador debe dejar de aceptar `token` viejo, no sólo limpiar cookies. Como el token actual se comparte por estación, inventariar/reautenticar todos los perfiles afectados por ese token; no permitir que una copia en otra fila mantenga la sesión.
- Tokens heredados sin fecha de emisión/expiración no se consideran válidos para siempre: si temporalmente se importan digests, limitar su autoridad al mínimo entre corte por cohorte y deadline global, con perfil/scope inequívocos y reautenticación obligatoria. La preferencia es no importarlos y forzar login coordinado.
- Revisión separada de sesiones web/refresh Portal3 y JWT humano: la publicación de la clave reversible no demuestra compromiso de las claves JWT, HMAC o Device Auth. Buscar reutilización/exposición mediante custodios y pruebas de revocación; rotar otras familias únicamente con evidencia o decisión específica del responsable. No rotarlas como parte de esta tarea. JWT V2 cortos pueden seguir válidos hasta `exp`; no afirmar revocación instantánea si el contrato actual no dispone de ella.
- Recuperación: administrador autorizado y reautenticado verifica propietario/centro mediante fuente interna confiable, prepara nueva versión y registra motivo sin secreto. No responder preguntas con el password antiguo ni enviar credenciales por chat/JSON. Perfiles desconocidos no se recuperan por conocer el ciphertext.
- La clave histórica se retira, no se reemplaza para seguir cifrando passwords. Secretos operativos nuevos, si se aprueban, se inyectan fuera del repositorio mediante gestión de secretos con permisos mínimos; nunca valores de ejemplo reutilizables como defaults de producción.

## 11. Pruebas de seguridad obligatorias para la futura implementación

Todos los ensayos con contraseñas/tokens usarán valores sintéticos en almacenamiento aislado. Los tests de API existentes que preparan tablas/filas no se ejecutaron en esta actividad de diseño.

| Grupo | Casos mínimos / resultado esperado |
|---|---|
| Password | Hashes con salts distintos; verify correcto/incorrecto; rehash; Argon2id ausente falla cerrado; bytes/Unicode/espacios idénticos en ambos lados; política server-side; sin truncamiento ni aceptación de hash como password |
| Compatibilidad | Registro/versiones explícitos; hash fallido jamás cae a legacy; plazo ausente/vencido deniega; cliente/cohorte falsificados no habilitan; duplicados/colisiones/mapeos ambiguos no usan `LIMIT 1` |
| API primero | Matriz API antigua/nueva × Portal3 antiguo/nuevo × perfil legacy/preparado/hash-only; combinaciones inseguras bloqueadas antes de escritura; todos los nodos API actualizados antes del primer reset |
| Autorización | Sesión faltante/CSRF inválido/acción sin ACL; lectura no permite reset; IDOR en perfil, operación, trabajador, export y sesión; cambio de `id_estacion`/localidad en body no amplía alcance |
| Centros | Puestos 6/13/3/14, estación inactiva/no soportada, `tb_estaciones.id != op_rh_localidades.id`, `numlista` ambiguo/ausente, Autolavado sin permiso explícito y sin sustitución por estación 11 |
| Sesiones | Tokens opacos distintos por perfil/sesión; digest únicamente en DB; expiración server-side; baja/reset invalida; carrera entre login y reset no emite versión vieja; token compartido histórico no sobrevive en otra fila |
| Concurrencia | Reset/activar/desactivar simultáneos, expected_version, rollback transaccional, ACK perdido, retry mismo UUID, UUID reutilizado con payload distinto; nunca doble activación ni contraseña en journal |
| Errores / abuso | SQL parametrizado; payloads malformados/tipos/longitud; errores DB/ACL/rate limiter → 503 sin login; 429/backoff, no bypass por proxy spoofing ni degradación al codec/credencial viejo |
| No exposición | Capturar respuestas JSON, DOM/DataTables, HTML, excepciones, trazas, exports, auditoría, proxy/APM y artefactos de tests: sin contraseña/ciphertext/hash/token de reset, Authorization o body; login token sólo en respuesta privada prevista |
| Gates de entorno | Matriz de flags §3.1, aliases históricos, seis rutas; GET 405 cuando deshabilitado; POST sin query fallback; preproducción continúa bloqueada con cero conexión/SQL legacy |
| Lector | Login POST, expiración/relogin, token único, catálogo/`idpersonal`, edición PIN/huella sólo autorizadas, asistencia legacy, cold start/offline, retry/429/503, caches; no pérdida ni ACK simulado |
| V2 independiente | `checador_api_jwt_isolation_regression.php`, configuración V8M, ACL/scope humano, Device Auth y vigencias, PunchEvents/idempotencia/integridad, acceso temporal; token legacy nunca autoriza V2 ni JWT humano autoriza dispositivo |
| Recuperación | Revertir a release segura conservando hash-only, versiones y revocaciones; restauración de backup no resucita credenciales o tokens; intento de downgrade denegado |

Reutilizar/adaptar los tests focalizados citados en §1, y agregar pruebas de comportamiento del contrato nuevo. Los PASS de fixtures no certifican API desplegada, datos reales o flota. La revisión de logging debe cubrir también infraestructura: el saneamiento de `app/Core/Logger.php` no cubre automáticamente acceso HTTP de API/proxy o APM.

## 12. Orden correcto de implementación y autorizaciones

1. **G0 — Aprobar diseño y responsables:** decidir A/B con evidencia de uso, política de password/TTL/deadline, modelo de identidad/mapeo, permisos y custodia de exposición. Aprobación documental no autoriza despliegue.
2. **G1 — Aprobar contrato físico y schema:** API owner + Portal3 + Lector/consumidores verifican metadatos y contrato candidato, prueba de reautenticación, unicidad, topología DB, permisos de servicio y rollback. No modificar Freeze; si cambia una decisión congelada, solicitar RFC arquitectónica aparte antes de implementar.
3. **G2 — Autorizar implementación local API:** migraciones aditivas como archivos, hashing/sesiones/estado/codec/administración y tests aislados; ninguna migración real ni producción por esa autorización.
4. **G3 — Autorizar implementación Portal3 y clientes:** adaptador nativo, protección de UI/logs y despliegue de versiones capaces; no escribir formato moderno hasta certificar todas las instancias API.
5. **G4 — Aprobar ensayo:** laboratorio legacy separado con datos sintéticos; preproducción V2 y regresiones independientes; simulación de corte, revocación, ACK perdido y rollback. Evidencia E2E por consumidores representativos.
6. **G5 — Autorizar despliegue/migración/rotación operativa:** autorización específica por entorno, cohorte y ventana, inventario completo y recuperación probada. La concede el propietario; no se deriva del PR #243 ni de este documento.
7. **G6 — Autorizar retiro/purga:** cero dependencia de comparador y codec, fin de ventana, perfiles migrados o retirados, revocación comprobada, secretos fuera de runtime y evidencias externas atendidas.

## 13. Despliegue gradual propuesto

| Fase | Acción posterior autorizada | Gate de avance |
|---|---|---|
| Preparación | Artefactos compatibles y backup cifrado/restringido; inventario, capacidad de hash/rate limit y control de versiones | No nodos antiguos capaces de escribir credenciales modernas; plan viable para toda la flota antes de iniciar plazo |
| Expandir schema | Persistencia aditiva inactiva, sin convertir passwords | Esquema consistente y verificado; sin alterar datos de asistencia/nómina/V2 |
| API | Instalar release capaz de ambos estados, nuevo session validator y kill switch cerrado; habilitar sólo configuración aprobada | Todos los nodos reconocen versiones; no hay fallback por fallo; métricas redactadas |
| Portal3 | Desplegar UI/adaptador API, bloquear escritor antiguo y mantener V2 independiente | ACL/scope/CSRF y no exposición verificados; API indisponible no escribe localmente |
| Clientes piloto | POST, reautenticación/expiración y codec según capacidad; credencial preparada | Todos los consumidores del perfil listos; capacidad de contingencia legacy validada |
| Activación piloto | Reset/activación atómica, revocación y reautenticación | Login/operaciones necesarias correctos, 0 acceso entre estaciones, 0 pérdida de captura atribuible; evaluación durante ciclo representativo |
| Cohortes siguientes | Repetir por perfil/estación con propietario y fecha | Métricas de error/latencia/reautenticación dentro de presupuesto aprobado; ningún secreto en telemetría |
| Contracción | Apagar GET/comparador antiguo y codec dependiente de clave, purgar ciphertext/tokens con aprobación | Todos los perfiles `HASH_ONLY` o retirados, cero consumidores viejos, deadline cumplido |

Métricas: resultados de autenticación, expiración/revocación, versiones de consumidor, rechazo por scope, latencia de hash, backlog/ACK y errores de dependencia, sin etiquetas con credenciales o URLs completas. Umbrales cuantitativos, tamaño de cohorte, ventana de observación y responsables se fijan en G0/G4; sin ello no se autoriza el piloto. Aislar el piloto por perfiles y rutas del servidor; una bandera del navegador no controla el despliegue.

## 14. Reversión y recuperación seguras

Detener promoción ante exposición de secreto, acceso fuera de scope, aceptación de credencial/token revocado, ambigüedad de identidad, pérdida de captura, fallos de reautenticación o presupuesto de error/latencia excedido. En incidente de seguridad, cerrar la cohorte afectada y mantener las fronteras V2 independientes.

- **Antes de activar hashes:** puede revertirse la UI o adaptador a una versión compatible con la API nueva, manteniendo schema aditivo y controles ya aplicados. No reactivar GET ni ampliar plazos como rollback automático.
- **Después de la primera activación:** el mínimo de rollback es una release de API/Portal3 que entienda `HASH_ONLY`, sesiones revocables y versiones. Está prohibido volver al API que compara sólo ciphertext, guardar la contraseña nueva de modo reversible, borrar la sidecar para disparar fallback o restaurar el token viejo. Una versión anterior insegura deja de ser recuperable operacionalmente.
- **Fallo de consumidor:** volver sólo a una versión segura compatible con hash y al codec permitido por esa cohorte. Si no existe, mantenimiento/contingencia aprobada y recuperación hacia adelante; no degradación silenciosa. Verificar por separado que exista retención durable para el flujo afectado; V2 no garantiza la de legacy.
- **Base de datos:** no ejecutar DOWN destructivo después de una activación. Backups con ciphertext son material sensible y no son una fuente de contraseñas recuperables. Ensayar restauración aislada con egreso y login cerrados; aplicar un registro durable de versiones/cortes/revocaciones conservado fuera del backup restaurado antes de abrir servicio. Si no puede reconstruirse autoridad actual, reset obligatorio y todas las sesiones afectadas inválidas.
- **Recuperación hacia adelante:** reparar API/cliente, preparar una nueva versión de credencial, revocar la fallida y reaprovisionar; preservar auditoría sin secretos. No reabrir deadline vencido como parte del proceso automático.

## 15. Condiciones de cierre

El diseño se entrega cuando existe esta propuesta, evidencia local trazable, A/B evaluadas y gates explícitos. El cierre de **remediación implementada** requerirá posteriormente:

1. Uso productivo acreditado y decisión A/B firmada; inventario completo, propietarios y mapeos resueltos, sin consumidores desconocidos con acceso.
2. Cero perfiles operativos con contraseña reversible; activos restablecidos y retirados bloqueados; ninguna duplicidad o mapeo ambiguo aceptado.
3. Validación robusta, ACL por módulo/acción, aislamiento por estación/localidad y ausencia de fallback inseguro comprobados en API y Portal3.
4. Cero passwords en JSON de respuesta, formularios precargados, URLs, logs, trazas, auditoría o artefactos; secretos de runtime fuera de repositorios y defaults.
5. Tokens heredados invalidados, sesiones nuevas con digest/expiración/revocación/versionado; cambio de password y baja no dejan sesiones válidas antiguas.
6. GET de credenciales retirado, compatibilidad expirada y deshabilitada en servidor, sin excepción indefinida.
7. Todas las referencias `idpersonal` dependientes de clave retiradas o sustituidas bajo contrato aprobado; clave ausente del runtime y código activo de ambos repositorios; copias históricas tratadas bajo custodia, sin afirmar que borrar Git descompromete secretos.
8. Consumidores/lectores y estaciones verificados por responsables con pruebas reales autorizadas; regresión V2 íntegra, sin alterar nómina ni hechos originales.
9. Ensayo gradual y rollback seguro aprobados; operación/alertas/documentación de recuperación entregadas, exposición externa evaluada y remediación verificada por seguridad.

### Validación realizada para este documento

- Revisión estática de los componentes y configuraciones no sensibles descritos; ningún dato de DB/producción consultado.
- `php tests/checador_v8m_configuration_regression.php`: **26 PASS / 0 FAIL / 0 SKIPPED**, proyecciones sintéticas y aserciones de fuente. No prueba login real ni hash nuevo.
- Matriz aislada temporal de la función actual de gate extraída de `index.php`: **16 PASS / 0 FAIL**, sin cargar handlers ni abrir DB/HTTP. Cubre perfil ausente/vacío/desconocido, preproducción y override, producción/histórico, dev/test, restricción secundaria, conflicto APP_ENV y flag GET incapaz de habilitar el gate.
- Búsqueda exacta redactada de exposición local y archivos ZIP/Base64 descrita en §2.1.
- No se ejecutaron suites API con fixtures persistentes, migraciones, pruebas remotas ni validación de credenciales reales. No hay implementación nueva que declarar probada.

## Anexo — reporte obligatorio

```text
TASK: CHECADOR_V2_LEGACY_CREDENTIAL_SECURITY_REMEDIATION_DESIGN

LEGACY_MECHANISM_DEPENDENCIES: Perfil compartido, comparador reversible, tokens opacos y codec de IDs de personal; ver secciones 1 y 5.
PORTAL3_COMPONENTS: BiometricosController, BiometricosService, RhLocalidadesPerfil, rutas/vista/JS de perfiles, ChecadorConfigurationReadService y regresion V8M.
API_COMPONENTS: index.php/gates, Encriptar, Acceso, Empresa, Personal y pruebas SEC-B/gate/alineacion; Device Auth y JWT V2 independientes.
LECTOR_IMPACT: POST login, reset/aprovisionamiento, expiracion/reautenticacion y codec/caches; codigo y flota NOT_VERIFIED.
PRODUCTION_LEGACY_AUTH_USAGE: NOT_VERIFIED

OPTION_A_FEASIBLE: CONDITIONAL_ON_PROVEN_NO_CONSUMERS; no acreditado actualmente.
OPTION_B_FEASIBLE: CONDITIONAL_ON_APPROVED_CONTRACT_SCHEMA_MAPPING_AND_CLIENT_READINESS.
RECOMMENDED_STRATEGY: OPTION_B_PREPARATION; evaluar A solo con evidencia operativa autorizada.
REASON: Hay dependencias ejecutables y evidencia documental de transicion incompleta; no es seguro asumir desuso ni cambiar el formato en sitio.

DATABASE_CHANGES_REQUIRED: YES_FOR_B_ADDITIVE; tipos/FK/topologia sujetos a metadatos aprobados; NONE_EXECUTED. A no necesita almacen nuevo si retira todo.
CREDENTIAL_RESET_REQUIRED: YES_FOR_RETAINED_LEGACY_PROFILES; perfiles retirados se deshabilitan; poblacion real NOT_VERIFIED.
TOKEN_REVOCATION_REQUIRED: YES_FOR_AFFECTED_LEGACY_SESSIONS_AT_CUTOVER; otras familias segun evidencia; NONE_EXECUTED.
BACKWARD_COMPATIBILITY_PLAN: API primero, POST/envelope compatible, autoridad por version, comparador temporal con deadline y sin fallback desde HASH_ONLY; codec por cohorte.
SECURITY_REGRESSION_PLAN: Seccion 11; hash/ACL/scope/no-exposicion/sesiones/idempotencia/gates/clientes/aislamiento V2/rollback.
DEPLOYMENT_SEQUENCE: G0-G6; schema aditivo -> API compatible completa -> Portal3 -> consumidores -> reset/activacion piloto -> cohortes -> retiro.
ROLLBACK_STRATEGY: Release segura compatible con hashes/versiones/revocaciones; mantener schema; nunca restaurar ciphertext, tokens viejos ni fallback vulnerable.

TECHNICAL_CONTRACT_PREPARED: YES_DRAFT_PENDING_APPROVAL; no contrato nuevo congelado ni endpoint implementado.
PROPOSED_DOCUMENT_PATH: docs/security/CHECADOR_V2_LEGACY_CREDENTIAL_REMEDIATION_PROPOSAL_V1.0.md

CODE_MODIFIED: NO
DATABASE_MODIFIED: NO
PRODUCTION_MODIFIED: NO
COMMITS_CREATED: NO
PUSH_EXECUTED: NO

PR243_READY_TO_MERGE: NOT_VERIFIED; este diseno no certifica el PR ni autoriza fusion.
NEXT_AUTHORIZATION_REQUIRED: Revision/aprobacion de la propuesta y G0/G1; autorizacion separada para implementar API, Portal3 y consumidores; produccion/DDL/reset/revocacion requieren G5 especifico.
FINAL_STATUS: DESIGN_COMPLETE_LOCAL_PENDING_APPROVAL; IMPLEMENTATION_NOT_STARTED.
```
