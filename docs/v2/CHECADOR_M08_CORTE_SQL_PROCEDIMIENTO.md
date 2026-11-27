# M08 — Procedimiento de corte del escritor administrativo

Preparación local; no autoriza activar el escritor ni efectuar el corte.

1. Pausar las operaciones administrativas humanas de acceso temporal.
2. Verificar la base efectiva de Portal3 y API, el esquema InnoDB, los índices, los permisos SQL y la equivalencia de ACL y jurisdicción.
3. Configurar el cierre del escritor humano API en todas sus instancias y drenar las operaciones en curso. Mantener disponibles la solicitud y validación del Lector.
4. Comprobar en cada instancia que las tres operaciones administrativas API devuelven `503 ADMINISTRATIVE_WRITER_UNAVAILABLE` sin mutaciones, mediante pruebas autorizadas y aisladas.
5. Solo después de acreditar el cierre API y obtener autorización operativa, habilitar `CHECADOR_M08_HUMAN_WRITER=PORTAL3` en Portal3 y verificar su configuración efectiva en todas sus instancias.
6. Validar con el propietario una solicitud del Lector, autorización, presentación única del PIN, validación, consumo y readback, así como rechazo, cancelación y aislamiento entre estaciones.
7. Ante un fallo, pausar nuevamente las operaciones humanas, drenar transacciones, cerrar el escritor Portal3 y restaurar únicamente el escritor API; verificar estados y readback antes de reabrir. No eliminar solicitudes ni historial y no mantener ambos escritores humanos activos simultáneamente.

La concurrencia entre autorización, cancelación y consumo requiere pruebas reales aisladas con InnoDB antes del corte. La respuesta de una autorización confirmada que se pierde no permite recuperar el PIN: cancelar y generar una nueva solicitud.
