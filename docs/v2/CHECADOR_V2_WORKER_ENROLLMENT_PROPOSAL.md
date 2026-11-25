# Checador V2 — alta e inscripción biométrica: propuesta

Estado: **propuesta pendiente de aprobación**. No modifica Architecture Freeze ni autoriza escrituras, endpoints o esquemas nuevos.

## Alta e identidad

El alta institucional conserva su responsable y sus validaciones. Una incorporación automática debe comprobar trabajador activo, estación física, mapeo unívoco a localidad canónica, jurisdicción y dispositivos habilitados. El número de colaborador requiere una decisión institucional sobre unicidad y tratamiento de duplicados existentes; no es la referencia externa del dispositivo.

El provisionador inicial existente está limitado a dispositivo 2 y estación 1, y usa el escritor canónico de identidades con UUID aleatorio. Para automatizar, se requiere acordar su generalización y propiedad de ejecución, el momento efectivo, atomicidad y recuperación de fallos entre alta e identidad. Conservar identidades y vigencias anteriores, bloquear el dispositivo como exige el escritor, rechazar ambigüedades y conflictos, y confirmar la identidad por lectura. No generar una identidad a partir del ID o número de colaborador.

## Solicitud y captura

Portal3 deberá autorizar al Encargado autenticado por permiso explícito y localidad, con CSRF, registrar una solicitud durable y auditable para trabajador y dispositivo autorizados, sin HTTP hacia API. Dirección conserva sólo consulta. La forma de intercambio y su estructura persistente, expiración, cancelación, idempotencia y responsable de escritura requieren aprobación; no se propone ningún endpoint ni tabla como existente.

El Lector Windows autenticado recibe la solicitud mediante el mecanismo acordado con API, confirma dispositivo, identidad y vigencia y captura la plantilla localmente. La API valida autorización y alcance otra vez; nunca confía en un trabajador o estación suministrado sin verificar. La plantilla no pasa por el navegador de Portal3, logs ni auditoría. Definir transporte cifrado, límites de tamaño/formato, protección en reposo, retención y quién puede leerla.

## Primera huella y reemplazo

La API será la única escritora biométrica si se aprueba esta responsabilidad. Tras validar solicitud, identidad y dispositivo, guardará la plantilla y confirmará readback dentro de una transacción; sólo después informará éxito y actualizará el estado de la solicitud. El reemplazo conserva la plantilla previa hasta que la nueva se haya validado y confirmado; un error revierte el cambio. Definir control de concurrencia, reintentos, estado final, evidencia y trazabilidad sin guardar biométricos. El catálogo del dispositivo se refresca sólo desde una fuente confirmada. La inscripción entre estaciones se rechaza.

El almacenamiento actualmente observado es `op_rh_personal_acceso.huella`; su historial, unicidad, protección y capacidad transaccional aún requieren verificación real. No se autoriza escritura ni sustitución de registros legacy hasta acreditar que soporta las garantías o aprobar el cambio mínimo necesario. Las rutas legacy de edición de huella no forman parte de esta propuesta.

## PIN temporal

El contrato congelado de acceso temporal contempla solicitud y validación desde Lector y autorización supervisora mediante API. Portal3 conserva actualmente llamadas HTTP heredadas para listar y autorizar; la directiva de SQL directo entra en conflicto con ese transporte. Cualquier sustitución o conciliación requiere aprobación específica y preservar TTL, un solo uso, idempotencia y trazabilidad `TEMPORARY_CODE`. No usar el PIN permanente legacy como contingencia.

## Validación pendiente

Antes de una prueba real: acordar el contrato y su almacenamiento; verificar ACL, adscripción, unicidad y mapeos en el runtime; implementar y probar identidad y captura en los tres componentes; verificar ventana de credencial, recuperación, error y aislamiento. La primera captura, actualización y dos marcaciones físicas corresponden al propietario; no fabricar eventos ni huellas.
