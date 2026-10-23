# Portal3 Checador V2 — gate UAT local V8K

**Fecha:** 2026-10-23
**Alcance:** hardening local del gate de operaciones POST y preparación de UAT; no es autorización de despliegue.

## Resultado

Los comandos reales de Checador quedan cerrados por defecto. Un operador sólo puede habilitarlos mediante `CHECADOR_API_REAL_POSTS_ENABLED=true` en un proceso `APP_ENV=dev|development|local`; el gate no abre en `preproduction` ni `production`. La guarda central ocurre antes de crear el JWT o llamar al transporte HTTP. El proveedor sintético permanece separado y puede responder localmente bajo su propio opt-in.

En el proceso CLI de Fedora comprobado para esta actividad: `APP_ENV` pertenece a desarrollo local, el modo demo está habilitado, la base API configurada tiene origen `https://api.admongas.com.mx`, la clave JWT dedicada está presente y el gate real POST está **CERRADO**. No se imprimieron valores secretos ni se hizo una llamada HTTP a la API. La presencia de una URL o clave no verifica autenticación, ambiente remoto ni ACL.

## Gates verificados

| Capacidad | Estado local de la interfaz/servidor | Límite comprobado |
|---|---|---|
| PIN temporal | La cola puede consultarse por su lectura existente si la ACL permite; autorizar/rechazar/cancelar no aparecen habilitados con el gate cerrado y el handler también lo rechaza. | `BLOCKED_LECTOR`: no se verificó origen, consumo ni runtime de Lector/API. No probar un PIN real. |
| Ajustes administrativos | Proyección informativa de referencias enlazadas; no hay ruta/formulario de mutación de ajustes. | `BLOCKED_API_CONTRACT`: detalle/historial completo y comandos no tienen frontera pública aprobada. |
| Cierre de periodos | Reportes conservados; no se encontró ruta/control de cierre o reapertura de Checador. | `BLOCKED_API_CONTRACT`: falta contrato, permiso e historial de cierre. |
| Comparación con Nómina | Sólo lectura, no monetaria y restringida por el servicio a Fedora/dev, host `fedora`, puerto 3306, base `bd_admongas`, doble ACL y localidad común. | `BLOCKED_ENVIRONMENT`: no se habilitará automáticamente en el subdominio; no se usa `sincronizarPersonal()`. |
| Incidencias, Journey y evaluación administrativa (POST) | Alta, transiciones, evaluación y creación de snapshot se cierran centralmente antes de JWT/HTTP; los flags de UI se cierran también. Demo usa respuestas locales, no el cliente real. | Opt-in real no configurado por esta actividad. Persistencia remota no probada ni autorizada. |
| Reporte raw, lecturas y CSV | SQL local de sólo lectura, estación/identidad/rango acotados; la autorización se revalida en servidor y CSV requiere `descargar`. GET de API conserva su ruta separada. | El scope de una persona autenticada requiere UAT con sesión; la verificación SQL no sustituye ACL. |

Las solicitudes POST de `ChecadorDailyReadService` pasan por `requestJson()`; la ruta de lectura HTTP `request()` se usa para GET. No se añadieron endpoints ni contratos. El gate real POST **no** se configura en `.env` por este cambio.

## UAT y lectura local

- La herramienta de navegador falló una vez al inicializar (`failed to write kernel assets`, filesystem de la herramienta); no se reintentó ni se inventó una sesión. No hay evidencia de UAT autenticada visual.
- Preflight read-only de `bd_admongas`: el runner inicialmente negó PDO (`SQLSTATE 2002 / Operation not permitted`). La ejecución autorizada y elevada del test, que confirma identidad de instancia y realiza SELECT solamente, terminó **27 PASS / 0 FAIL**.
- La instancia respondió como `bd_admongas@fedora:3306`. Estación física `tb_estaciones.id=1`, `numlista=2`, tiene una sola correspondencia `op_rh_localidades.numlista=2`, `op_rh_localidades.id=1`.
- El trabajador canónico `op_rh_personal.id=558` **no existe** en esta base local. En el rango UTC de prueba 2026-02-01 a 2026-03-01 se consultaron tres filas de estación/localidad 1; la identidad y la hora de ocurrencia están sin resolver. El filtro canónico 558 no atribuye esas filas. Esto no valida el evento piloto remoto ni una identidad de usuario de Portal3.
- Sin sesión no se confirmó el alcance efectivo de Dirección/Encargado ni la ACL real de descarga. Las regresiones con fixtures de acceso pasan.

### Recorrido manual (máximo ocho casos)

Usar únicamente una cuenta legítima de desarrollo. Capturar URL/fecha/estación, estado visible y evidencia redactada; ocultar nombres, tokens y datos personales no necesarios. PASS exige que la pantalla, endpoint y exportación coincidan en alcance; cualquier cruce de estación, éxito de escritura con gate cerrado o inferencia de ausencia es FAIL.

| # | URL local existente | Perfil y acción | Esperado / evidencia para PASS |
|---|---|---|---|
| 1 | `/departamento-operativo/recursos-humanos/checador` | Dirección (puesto 13): abrir selector y elegir una localidad efectivamente autorizada. Intentar una localidad fuera de alcance sólo por URL. | Selector contiene sólo alcance del servidor; consulta autorizada funciona y la ajena devuelve denegación. Guardar captura de ambas respuestas y URL. |
| 2 | Misma ruta | Encargado (puesto 6) y luego usuario 470: inspeccionar selector; probar una localidad ajena por URL. | Sólo localidad principal; la excepción multiestación general no amplía Checador. Guardar captura del selector y resultado de denegación. |
| 3 | `/departamento-operativo/recursos-humanos/checador/reporte` | Dirección con una estación autorizada: consultar `2026-02-01`–`2026-02-28` (UTC), luego filtrar trabajador 558. | Se muestran sólo filas observadas. La consulta de referencia encontró tres filas sin identidad/hora de ocurrencia resuelta; el filtro 558 no las atribuye. Capturar conteo y filtros. |
| 4 | Misma ruta y `/reporte/data` | Dirección con `descargar`: ordenar/paginar y exportar CSV con filtros activos; repetir sin permiso de descarga con otra cuenta autorizada a leer. | CSV corresponde al conjunto completo filtrado y su permiso se vuelve a comprobar; sin `descargar` debe denegarse. Guardar cabecera/conteo, no datos personales. |
| 5 | `/departamento-operativo/recursos-humanos/checador?schedule_history=1&workplace_id={localidad}&worker_id={trabajador}&date={fecha}` | Dirección o Encargado con ese trabajador/localidad en alcance; consultar historial, incluyendo turno nocturno si existe fuente. | Vigencias y fecha laboral usan la fuente contractual; desconocido/incompleto no se presenta como descanso o falta. Capturar fecha, timezone y resultado sin reinterpretarlos. |
| 6 | `/departamento-operativo/recursos-humanos/checador?workplace_id={localidad}&date={fecha}` | Usuario autorizado; confirmar banner demo y revisar Nivel 1/2, horario, Journey/P3-H e incidencias sintéticas. | Datos se identifican como demo y no se mezclan con el reporte SQL real; sin solicitudes reales a API para escenarios demo. Capturar banner/estado y log de red redactado. |
| 7 | Misma ruta; POST de incidencia/evaluación/transición sólo con gate cerrado | Usuario con permisos de lectura y/o acción; inspeccionar controles y no enviar una operación real. | Las escrituras reales permanecen ocultas/deshabilitadas y el servidor las rechaza si se intenta la ruta con gate cerrado; CSRF/ACL no se saltan. No capturar ni enviar secretos. |
| 8 | `/departamento-operativo/recursos-humanos/checador/comparacion-nomina` | Usuario con lectura de Checador y Nómina; consultar sólo en Fedora local. | Vista informativa no monetaria, localidad común, sin modificación ni conclusión de nómina. En subdominio/preproducción no debe activarse con la fuente local. |

Las rutas son las registradas en `routes/web.php`; no se verificaron en navegador. No ejecutar alta, transición, evaluación, PIN, snapshot ni otra operación persistente como parte del UAT de lectura.

## Matriz ambiental para el subdominio

| Elemento | Fedora desarrollo | Subdominio preproducción | Producción |
|---|---|---|---|
| `APP_ENV` | Desarrollo local verificado en CLI; PHP-FPM visual no verificado. | Debe configurarse explícitamente como preproducción y verificarse desde runtime. | Sin cambios en este slice. |
| Demo | `CHECADOR_ADVANCED_DEMO_ENABLED` efectivo: sí; fixtures aislados. | Debe estar ausente/falso. | Debe estar ausente/falso. |
| POST real Checador | `CHECADOR_API_REAL_POSTS_ENABLED` efectivo: cerrado. Sólo opt-in local explícito. | Debe permanecer cerrado; el código también lo cierra cuando `APP_ENV` no es local/dev. | Cerrado por código. |
| API/JWT | Origen configurado observado: `https://api.admongas.com.mx`; secreto dedicado presente sólo como booleano. Sin HTTP ni validación de entorno/claims. | Requerir URL del ambiente aprobada, PHP-FPM, TLS, `CHECADOR_API_JWT_SECRET` exclusivo y permisos/identidad verificados; nunca usar `JWT_SECRET` global como fallback. | Sin configuración ni llamadas. |
| SQL de marcaciones | `bd_admongas@fedora:3306`, SELECT local verificado; no demuestra que sea origen API autoritativo remoto. | Requiere conexión privada separada, TLS/aislamiento según arquitectura, usuario de sólo lectura y prueba de host/esquema runtime. No usar credenciales Portal3 de escritura. | No definido ni tocado. |
| Comparación nómina | Guard local verificado por código/pruebas. | No disponible con el guard actual; no quitarlo como atajo. | Deshabilitada. |
| Runtime | PHP CLI 8.5.10; `curl`, `json`, `mbstring`, `openssl`, `PDO`, `pdo_mysql` presentes. | Verificar versión/extensiones en PHP-FPM, DocumentRoot `public`, rutas/assets, logs y variables sin revelar secretos. | Sin cambios. |

## Pruebas de esta actividad

Todas las solicitudes API de regresión usaron transportes/mock locales; no se llamó a la API. No se ejecutó una suite general.

| Prueba focalizada | Resultado |
|---|---:|
| `checador_real_api_post_gate_regression.php` | 8 PASS / 0 FAIL |
| `checador_mvp_punch_report_local_readonly_integration.php` | 27 PASS / 0 FAIL; SELECT real local |
| `checador_access_foundation_regression.php` | 51 PASS / 0 FAIL |
| `checador_p3g_incidence_creation_regression.php` | 17 PASS / 0 FAIL |
| `checador_demo_incidence_lifecycle_regression.php` | 16 PASS / 0 FAIL |
| `checador_demo_admin_evaluation_regression.php` | 17 PASS / 0 FAIL |
| `checador_temporary_access_portal_regression.php` | 13 PASS / 0 FAIL |
| `checador_payroll_comparison_readonly_regression.php` | 39 PASS / 0 FAIL |
| P03 / P3G-C / P3G-D / P04C / P04D / P3G-A / raw report | Se detectaron fallos aislados de aserciones estáticas amplias, no defectos funcionales confirmados: P319/P3GPC28/P4D34/P3GA24 buscan “Nómina” en un enlace informativo; P3GPD33/P4C25 tratan referencias legítimas a comparación de lectura como efecto salarial; P4C12 confunde `date('Y')` con un `DATE()` SQL; la aserción raw report incluye el método hermano `punchReportData()`, que consulta el modo demo pero no la API raw. No se modificaron esas pruebas heredadas. |

## Archivos y cierre

Cambios de esta actividad, sobre un worktree que ya tenía modificaciones del usuario: `ChecadorDailyReadService.php`, `ChecadorController.php`, vista principal de Checador, prueba enfocada del gate POST, ajuste de setup de la prueba PIN y este documento. No se alteraron los contratos/freeze, API, Lector, ACL, Plesk ni datos. No se creó commit.

**UAT autenticada:** pendiente.
**Integración API real:** no verificada.
**Preparación de despliegue al subdominio:** `BLOCKED` hasta verificar sesión/ACL, ambiente PHP-FPM, API destino, identidad/scope, fuente SQL de sólo lectura y completar UAT; no es autorización de deploy.
**Siguiente intervención mínima:** corregir con alcance de método las aserciones estáticas listadas; después ejecutar el recorrido manual de UAT con perfiles reales y, por separado, aprobar un preflight GET de API y la conectividad SQL privada del subdominio.
**DATABASE_MODIFIED:** `NO`
**PRODUCTION_MODIFIED:** `NO`
**ARCHITECTURE_FREEZE_UNCHANGED:** `YES`
