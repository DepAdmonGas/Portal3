# Checador V2 — desarrollo con datos sintéticos

Fecha: 2026-10-21  
Alcance: únicamente el reporte MVP de marcaciones crudas en Portal3 local.

## Destino y aislamiento

- Base: `bd_admongas`, instancia local Fedora `fedora:3306`, `APP_ENV=dev`.
- Tabla: `op_rh_v2_marcaciones_crudas`.
- Los registros originales existentes (7 antes de la carga) se conservaron; no se ejecutaron `UPDATE` ni `DELETE`.
- Se agregaron 67 filas con el marcador `CHECADOR_V2_SYNTHETIC_DATA_FUNCTIONAL_COMPLETION_V1` en `metadata_json` y el prefijo `SYNTHETIC-CHECADOR-V1-` en `event_uuid` (una fila inicia con `=SYNTHETIC...` para comprobar protección de fórmulas CSV).
- Los registros usan los dispositivos de desarrollo 100/101 de la estación 1 y las identidades sintéticas de desarrollo ya existentes 325/999. No se crearon ni modificaron colaboradores, dispositivos, mapeos, permisos ni tablas.
- No se insertaron filas en otra estación porque no se verificó un segundo dispositivo válido para el catálogo Checador. El aislamiento entre estaciones se comprobó mediante los controles y fixtures de autorización existentes.

## Escenarios

- 64 filas dentro de UTC `2026-10-01` a `2026-11-01` (límite superior exclusivo); 3 filas de septiembre fuera del periodo.
- Dos dispositivos, dos identidades sintéticas existentes y eventos sin identidad resuelta.
- Cinco filas con hora de ocurrencia no determinada y doce sin colaborador resuelto.
- Horas de recepción retrasadas con `CLOCK_DRIFT_ALERT`; métodos de captura permitidos por el contrato (`BIOMETRIC`, `PIN`, `CARD`, `NFC`, `MOBILE`, `WEB_TERMINAL`); estados generados por el flujo (`RECEIVED`, `PENDING_MATCH`).
- `event_uuid` estable por fixture y persistencia mediante `PunchEventV2::registrarPunchEvent`, que aplica la clave de idempotencia existente `(id_dispositivo, event_uuid)`. La carga se verificó con conteo y readback antes del commit de la transacción.
- No se generaron ni fabricaron sellos HMAC. Las 67 filas quedan sin sello y Portal3 las presenta como “Integridad no verificada por Portal3”.

## Resultados y límites

- La tabla es InnoDB y conserva sus triggers `BEFORE UPDATE`/`BEFORE DELETE`; la unicidad dispositivo/evento se mantiene.
- La carga no altera el esquema ni habilita datos operativos: todas las filas son identificables por marcador y prefijo sintético.
- El reporte sigue usando UTC; no se consulta la zona horaria reportada por dispositivo como autoridad laboral. La conversión IANA se verificó en un fixture aislado (`America/Mexico_City`) y no se presenta como configuración laboral de una estación.
- No se accedió a Plesk, preproducción ni producción; no se modificaron API ni Lector.
- No se creó commit.
