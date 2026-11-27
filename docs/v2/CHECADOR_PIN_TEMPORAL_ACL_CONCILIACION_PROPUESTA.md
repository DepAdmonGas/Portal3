# PIN temporal — conciliación de autorización propuesta

**Estado: propuesta para coordinación; no activa.** La decisión del propietario es reutilizar Checador y no crear configuraciones ni registros adicionales para PIN temporal. Esta propuesta concilia, sin modificarlos, `TEMPORARY_ACCESS_API_CONTRACT` y `TEMPORARY_ACCESS_SECURITY_ADDENDUM_V1.0`, especialmente sus §§3–4, que actualmente requieren módulo, permisos y alcance propios. El contrato congelado exige que API y Portal3 mantengan la misma autorización; la implementación actual de API vuelve a exigir dichas filas. No activar solamente Portal3 ni interpretar este documento como modificación unilateral de API.

## Autorización propuesta coordinada

1. Resolver usuario institucional activo y sesión válida; comprobar permiso efectivo de lectura del módulo institucional Recursos Humanos y asignación activa al submódulo Checador mediante `ChecadorAccessService::authorize()`. Aplicar el mismo algoritmo y precedencia en API, con identidad autenticada y lectura fresca desde SQL.
2. El alcance permitido es exclusivamente `station_workplaces` de Checador; validar estación física activa y correspondencia unívoca por `numlista` con localidad canónica. Nunca aceptar `allow_all`, departamentos por inferencia ni estación proveniente sólo del cliente.
3. Puesto 6 puede consultar. Para autorizar, rechazar o cancelar, requiere además permiso efectivo `editar` del módulo institucional Recursos Humanos, verificado en servidor. Puesto 13 sólo consulta. Otros puestos denegados. Un grant de lectura, puesto o presencia en submenú no concede escritura.
4. Cuando exista un grant individual explícito de `acceso-temporal`, sus flags negativos prevalecen sobre la capacidad heredada. Una falla de lectura del ACL es denegación; ausencia de fila no equivale a denegación explícita. Las mismas reglas deben aplicarse en Portal3 y API y respetar los grants individuales del módulo padre. No borrar ni insertar registros. Tratar módulos inactivos, filas duplicadas y permisos de usuario en otros módulos conforme a una regla única y probada en ambos proyectos antes de activar; no resolver divergencias con un fallback permisivo.
5. En cada petición validar identidad, puesto, permisos, estación y localidad. Las solicitudes del Lector conservan Device Auth y el contrato actual. Portal3 consulta y ejecuta mediante las rutas API existentes; no se cambia el transporte del PIN de asistencia ni la semántica de un solo uso.

## Gate de POST

Diseñar un control separado aplicado únicamente a `authorize`, `reject` y `cancel` de acceso temporal, tanto en el controlador como en el adaptador HTTP. El control debe dejar cerradas las demás operaciones cubiertas por `CHECADOR_API_REAL_POSTS_ENABLED`, fallar cerrado ante configuración/runtime no verificados y no reemplazar ACL, CSRF, confirmación ni validación de solicitud y estación. Se requiere acordar mecanismo operacional y condiciones del runtime con el propietario antes de habilitarlo. La puerta compartida actual no se abre como efecto de esta propuesta.

## Transición y pruebas

Antes de activar: acordar y documentar la revisión contractual con responsables de API y Portal3; implementar el mismo ACL en API mediante cambio autorizado separado; verificar configuraciones efectivas, JWT y mapeos en el runtime; comprobar permisos y denegaciones reales. Ensayar en entorno aislado: puesto 6 sin fila adicional, denegación individual, estación ajena, puesto 13 sólo lectura, otros puestos, usuario inactivo, URL directa, solicitud de otra estación y fallos de dependencia. Probar que el gate de PIN no abre otros POST. El propietario valida posteriormente en Chrome. No se realizan escrituras de configuración por esta propuesta.
