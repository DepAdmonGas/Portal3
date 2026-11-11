# Catálogo de destinos de Rol de Comodines

**Fuente empresarial aprobada:** ocho localidades canónicas que operan como estaciones de servicio: `1, 2, 3, 4, 5, 6, 7, 14`.

La elegibilidad se define exclusivamente en `RolComodinesService::ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS`. El cruce físico de cada ID se verifica contra el par revisado de `numlista` y `tb_estaciones.id`; cualquier ausencia, duplicidad o cambio del par retira ese destino del selector y hace que solicitud/aprobación fallen de forma segura hasta revisar el mapeo.

La lista empresarial prevalece para esta regla específica porque las ocho estaciones confirmadas operativas tienen `tb_estaciones.estatus=0`, aunque el catálogo institucional define ese valor como cancelado. Comodines no invierte esa semántica ni la extiende a otros módulos. El propietario operativo debe retirar del catálogo de destinos toda estación que deje de operar; este control manual es necesario hasta que el estado institucional contradictorio se regularice.

## Retirar un destino

1. Con confirmación empresarial de que la estación dejó de operar, retirar su ID canónico de `ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS` en `RolComodinesService`.
2. Conservar el dato del cruce revisado como referencia; no cambiar `tb_estaciones.estatus` desde Comodines.
3. Verificar que el destino ya no aparezca en las opciones y que solicitud y aprobación lo rechacen. La cancelación de una cobertura existente no consulta la lista y debe seguir disponible conforme a sus permisos y controles.
4. Las coberturas ya autorizadas no se cancelan automáticamente: su estado efectivo requiere una transición explícita de cancelación o el fin normal de vigencia.
5. Para readmitirlo, exigir una nueva decisión empresarial y revalidar la localidad canónica, `numlista`, estación física única y su estado operativo antes de actualizar la lista y el cruce revisado.

El alcance efectivo de Dirección, la membresía canónica, el padrón de trabajadores autorizado, las plantillas versionadas, los conflictos, la idempotencia y la auditoría se validan aparte y no cambian con este catálogo.
