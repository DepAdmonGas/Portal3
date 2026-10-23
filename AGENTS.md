# Portal3 — instrucciones del checkout

## Desarrollo nativo

- Toma como referencia el código vigente de este checkout; antes de implementar, localiza entre uno y tres módulos comparables y revisa sus rutas, controladores, servicios/modelos, vistas, recursos y pruebas.
- Mantén la estructura MVC, los namespaces, el estilo PHP y las convenciones de rutas y recursos que realmente use el módulo de referencia. Reutiliza layouts, servicios, componentes, estilos y JavaScript existentes; no introduzcas un sistema visual, framework o arquitectura paralela.
- Para cambios funcionales o visuales de Portal3, aplica `.agents/skills/portal3-native-module/SKILL.md`. Para Checador V2, aplica además `.agents/skills/checador-v2-functional-slice/SKILL.md`.
- Protege autenticación, permisos y alcance en el servidor; no confíes en identificadores o controles sólo porque vienen de la interfaz. Mantén etiquetas, errores, estados vacíos y diseño adaptable coherentes con Portal3.
- Ejecuta pruebas focalizadas y validación de sintaxis pertinentes. Distingue resultados simulados, locales reales y remotos reales; nunca presentes fixtures como verificación de una fuente real.

## Límites

- Preserva cambios preexistentes del worktree. No limpies, restaures, prepares ni confirmes cambios ajenos a la tarea.
- No modifiques API, Lector, Plesk, producción, datos, ACL, migraciones o contratos congelados sin autorización expresa y específica.
- `docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md` es inmutable salvo aprobación arquitectónica expresa.
- Implementa sólo el alcance solicitado; si falta un contrato, permiso o fuente autoritativa, falla de forma segura y reporta el bloqueo en vez de inventar resultados.
