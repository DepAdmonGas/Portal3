# Portal3 — instrucciones del checkout

## Desarrollo nativo

- Toma como referencia el código vigente de este checkout; antes de implementar, localiza entre uno y tres módulos comparables y revisa sus rutas, controladores, servicios/modelos, vistas, recursos y pruebas.
- Mantén la estructura MVC, los namespaces, el estilo PHP y las convenciones de rutas y recursos que realmente use el módulo de referencia. Reutiliza layouts, servicios, componentes, estilos y JavaScript existentes; no introduzcas un sistema visual, framework o arquitectura paralela.
- Para cambios funcionales o visuales de Portal3, aplica `.agents/skills/portal3-native-module/SKILL.md`. Para Checador V2, aplica además `.agents/skills/checador-v2-functional-slice/SKILL.md`.
- Protege autenticación, permisos y alcance en el servidor; no confíes en identificadores o controles sólo porque vienen de la interfaz. Mantén etiquetas, errores, estados vacíos y diseño adaptable coherentes con Portal3.
- Ejecuta pruebas focalizadas y validación de sintaxis pertinentes. Distingue resultados simulados, locales reales y remotos reales; nunca presentes fixtures como verificación de una fuente real.

## Estándar de UI y reutilización de código

- **Contrato obligatorio:** antes de cualquier cambio en vistas PHP, CSS, JavaScript, tablas, botones, modales, layouts o navegación, leer `docs/ui/PORTAL3_UI_CONTRACT.md` y las skills actuales `.agents/skills/portal3-native-module/SKILL.md` y, para Checador, `.agents/skills/checador-v2-functional-slice/SKILL.md`. Inspeccionar el checkout local y el CSS Modernize real; el contrato es guía, no sustituto del código.
- Modernize, Bootstrap 5 y Tabler Icons instalados son la primera opción. Para acciones de tabla, aplicar la regla por cantidad de acciones de `docs/ui/PORTAL3_UI_CONTRACT.md`: botón directo para una, dropdown nativo para dos o más, y ningún botón si no hay acciones disponibles. Omitir opciones sin permiso; no presentarlas deshabilitadas. Centrar sólo el control de la celda Acciones; nunca datos, contenido de modal ni contenedores compartidos. No crear CSS/componentes paralelos.
- Antes de editar una UI, registrar `CAMBIO_PEDIDO`, `ELEMENTOS_PROTEGIDOS`, `PATRÓN_EXISTENTE_A_REUTILIZAR` y `CÓMO_SE_VALIDARÁ`. Si se necesita CSS excepcional, limitarlo al componente y justificar por qué las clases existentes no bastan.
- Después de editar, revisar el diff completo y validar en navegador a 375, 768, 1024 y 1440 px si está legítimamente disponible. Si no se inspecciona en navegador, indicar `VISUAL_NOT_VERIFIED`; lint y pruebas sintéticas no verifican diseño. No declarar `PASS` visual sin evidencia y aprobación cuando aplique.
- Antes de crear o adaptar una tabla, modal, formulario o dropdown, localiza un patrón equivalente en el checkout y consulta [el catálogo de componentes de referencia](docs/ui/PORTAL3_COMPONENTES_REFERENCIA.md). Usa su código como fuente de estructura y dependencias; adapta sólo los campos, textos, rutas y comportamiento propios del módulo.
- **Instrucción obligatoria:** "Antes de escribir una vista o componente, identificar el patrón canónico existente del mismo tipo. Reutilizar su estructura y clases. Si no existe referencia aplicable, reportar la excepción en lugar de inventar un nuevo diseño."
- Para cada cambio, identifica una referencia funcional concreta y otra visual concreta (pueden ser el mismo módulo si cubren ambas); registra sus archivos/rutas y explica cualquier diferencia necesaria. Reutiliza el stack que ya carga el layout o módulo: PHP MVC/FastRoute, Modernize, Bootstrap 5, Alpine.js, Axios y DataTables según el uso existente, JavaScript compartido, modales Bootstrap e iconografía Portal3. No incorpores React, Vue, otro framework CSS ni bibliotecas/componentes nuevos.
- Conserva la estructura MVC, layouts, paso de variables, escape, validación, autorización y CSRF que usa el módulo comparable. No inventes otra forma de cargar vistas ni agregues contenedores decorativos que el patrón no necesite.
- Usa Bootstrap, Tabler, componentes y recursos ya cargados por el layout/controlador. Reutiliza `styles.css` y hojas existentes; no agregues CSS inline, frameworks, dependencias ni reglas globales para imitar componentes existentes.
- Para JavaScript, conserva los inicializadores y convenciones del módulo de referencia. Evita inicializaciones dobles, listeners duplicados y lógica paralela para componentes que Bootstrap o DataTables ya gestionan.
- Prefiere cambios quirúrgicos y no alteres componentes globales ni módulos de referencia como parte de una adaptación local.
- En cada cambio funcional o visual, valida el recorrido y sus permisos con pruebas focalizadas; ejecuta `php -l` y `node --check` para los lenguajes modificados. Compara estructura/clases con la referencia canónica. Si hay navegador autorizado, revisa consola y presentación a 375, 768 y 1440 px; si no está disponible, registra la revisión visual pendiente sin evadir la restricción ni declarar evidencia no observada.
- En el reporte de cada cambio UI, identifica el archivo y bloque fuente, dependencias CSS/JavaScript, adaptaciones realizadas y toda diferencia estructural con su motivo. No declares reutilización por mera similitud visual.

## Límites

- Preserva cambios preexistentes del worktree. No limpies, restaures, prepares ni confirmes cambios ajenos a la tarea.
- No modifiques API, Lector, Plesk, producción, datos, ACL, migraciones o contratos congelados sin autorización expresa y específica.
- `docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md` es inmutable salvo aprobación arquitectónica expresa.
- Implementa sólo el alcance solicitado; si falta un contrato, permiso o fuente autoritativa, falla de forma segura y reporta el bloqueo en vez de inventar resultados.
