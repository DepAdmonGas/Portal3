# BASE DE DATOS Y TABLAS

> Referencia completa de las **4 tablas** que intervienen en la multiestación, con sus columnas,
> para qué sirve cada una, y los **valores reales** en producción (consultados a la BD).
>
> Leé esto cuando el "Paso 1" de la guía (`02_IMPLEMENTACION_PASO_A_PASO.md`) lo pida, o cuando
> necesites configurar permisos.

---

## Las 4 tablas de un vistazo

| Tabla | Qué guarda | Rol |
|---|---|---|
| `tb_modulos_config` | Configuración **por módulo** | Qué estaciones soporta cada módulo y cómo se ve su menú |
| `tb_estaciones` | Catálogo de **estaciones** | Los datos de cada gasolinera (nombre, RFC, política...) |
| `tb_multiestacion_puesto` | Estaciones **por puesto** | Qué estaciones pueden ver los usuarios de cada puesto |
| `tb_multiestacion_usuario` | Estaciones **por usuario** | Excepción individual sobre un usuario (gana sobre el puesto) |

**La fórmula del sistema:**
```
estaciones disponibles del módulo =
      tb_modulos_config.estaciones_soportadas   (qué soporta el módulo)
      ∩                                        (intersección)
      estaciones permitidas del usuario          (puesto → usuario)
```

---

## 1 · `tb_modulos_config` — configuración por módulo

### Qué es y para qué sirve
Dice, para **cada módulo**, cuáles son las estaciones que puede mostrar y cómo se ve su menú.
Sin fila aquí → el módulo no tiene multiestación.

### Columnas y su propósito

| Columna | Para qué sirve |
|---|---|
| `id` | Identificador (autoincrement) |
| `modulo_key` | Nombre único del módulo (**clave para conectar todo**: BD = Controller = Vista = JS) |
| `tipo` | Enum: `'stations_only'` (solo estaciones) o `'stations_and_departments'` (estaciones + departamentos) |
| `estaciones_soportadas` | Lista JSON de IDs de estaciones que el módulo usa |
| `departamentos_soportados` | Lista JSON de departamentos (solo si `tipo = 'stations_and_departments'`) |
| `tipo_departamento` | Enum opcional `'localidades'` / `'puestos'` (tipo de departamento) |
| `allow_all` | `1` = mostrar opción "Todas las estaciones"; `0` = no |
| `placeholder` | Texto del menú sin elegir ("Selecciona una estación...") |
| `activo` | `1` = habilitado |

> Restricción de la columna `tipo`: solo acepta **`'stations_only'`** o
> **`'stations_and_departments'`**. Cualquier otro valor (como `'stations'`) queda vacío y
> rompe el selector.

### Valores reales en producción

| id | modulo_key | tipo | estaciones_soportadas | allow_all | activo |
|---|---|---|---|---|---|
| 19 | `sasisopa` | `stations_only` | `[1,2,3,4,5,6,7,9,11,12,14]` | 0 | 1 |
| 20 | `sgm` | `stations_only` | `[1,2,3,4,5,6,7,9,11,12,14]` | 0 | 1 |

**INSERT para un módulo nuevo (re-ejecutable):**

```sql
INSERT INTO tb_modulos_config
    (modulo_key, tipo, estaciones_soportadas, departamentos_soportados,
     tipo_departamento, allow_all, placeholder, activo)
VALUES
    ('mi-modulo', 'stations_only', '[1,2,3,5]', NULL,
     NULL, 0, 'Selecciona una estación...', 1)
ON DUPLICATE KEY UPDATE
    tipo                  = VALUES(tipo),
    estaciones_soportadas = VALUES(estaciones_soportadas),
    allow_all             = VALUES(allow_all),
    placeholder           = VALUES(placeholder),
    activo                = VALUES(activo);
```

**INSERT combinado de los dos módulos reales (SASISOPA y SGM):**

```sql
INSERT INTO tb_modulos_config
    (modulo_key, tipo, estaciones_soportadas, departamentos_soportados,
     tipo_departamento, allow_all, placeholder, activo)
VALUES
    ('sasisopa', 'stations_only', '[1,2,3,4,5,6,7,9,11,12,14]', NULL,
     NULL, 0, 'Selecciona una estación...', 1),
    ('sgm',      'stations_only', '[1,2,3,4,5,6,7,9,11,12,14]', NULL,
     NULL, 0, 'Selecciona una estación...', 1)
ON DUPLICATE KEY UPDATE
    tipo                  = VALUES(tipo),
    estaciones_soportadas = VALUES(estaciones_soportadas),
    allow_all             = VALUES(allow_all),
    placeholder           = VALUES(placeholder),
    activo                = VALUES(activo);
```

**Variantes de `tipo` (para módulos que no son solo estaciones):**

```sql
-- Variante B · Estaciones + Departamentos (ej: seguros)
-- → departamentos_soportados con IDs de departamentos, tipo estándar
INSERT INTO tb_modulos_config
    (modulo_key, tipo, estaciones_soportadas, departamentos_soportados,
     tipo_departamento, allow_all, placeholder, activo)
VALUES
    ('seguros', 'stations_and_departments', '[1,2,3,4,5,6,7,8]', '[4,5,18,19,23]',
     NULL, false, 'Selecciona una estación...', 1);

-- Variante C · Módulos tipo localidades (ej: op_rh_localidades)
-- → tipo_departamento = 'localidades': usa op_rh_localidades en vez de tb_estaciones
INSERT INTO tb_modulos_config
    (modulo_key, tipo, estaciones_soportadas, departamentos_soportados,
     tipo_departamento, allow_all, placeholder, activo)
VALUES
    ('op-rh-localidades', 'stations_and_departments', '[1,2,3,4,5,6,7,8]', NULL,
     'localidades', false, 'Selecciona una localidad...', 1);
```

> Ejecutá **solo el bloque que corresponde a tu módulo** (solo-estaciones, estaciones+departamentos
> o localidades). Los bloques con `ON DUPLICATE KEY UPDATE` son re-ejecutables.

---

## 2 · `tb_estaciones` — catálogo de estaciones

### Qué es y para qué sirve
El **catálogo maestro** de gasolineras. Todo ID de estación de las otras tablas corresponde a una
fila de aquí. También guarda la información propia de cada estación (política, misión, RFC...) que
usan las páginas (ej: la política en SASISOPA).

### Columnas principales

| Columna | Para qué sirve |
|---|---|
| `id` | **El ID que se usa en todos los JSON** de estaciones |
| `numlista` | Número de lista (interno) |
| `nombre` | Nombre de la estación |
| `estatus` | `0` (activa) / otro valor (inactiva) |
| `politica`, `mision`, `vision` | Textos que muestran las páginas por estación |
| `razonsocial`, `rfc`, `direccioncompleta` | Datos legales/fiscales |

### Catálogo real (IDs que verás en los JSON)

| id | nombre | id | nombre |
|---|---|---|---|
| 1 | Interlomas | 8 | Comodines |
| 2 | Palo Solo | 9 | Ventura Puente |
| 3 | San Agustin | 10 | DEMO |
| 4 | Gasomira | 11 | Sabino aguirre |
| 5 | Valle de Guadalupe | 12 | Acueducto de Guadalupe |
| 6 | Esmegas | 13 | Tultepec |
| 7 | Xochimilco | 14 | Bosque Real |

---

## 3 · `tb_multiestacion_puesto` — estaciones por puesto

### Qué es y para qué sirve
Define qué estaciones puede ver **cada puesto** (rol). Si un puesto no tiene fila aquí, se usa la
configuración por usuario (abajo).

### Columnas

| Columna | Para qué sirve |
|---|---|
| `id` | Identificador |
| `id_puesto` | El puesto (único por fila) |
| `estaciones` | Lista JSON de estaciones permitidas para eses puesto |
| `departamentos_puestos` | Departamentos por puesto (para `stations_and_departments`) |
| `departamentos_localidades` | Departamentos por localidad (para ese tipo) |
| `activo` | `1` = habilitado |

### Valores reales en producción

| id | id_puesto | estaciones | activo |
|---|---|---|---|
| 2 | 13 | `[1,2,3,4,5,6,7,14]` | 1 |
| 5 | 3 | `[1,2,3,4,5,6,7,14]` | 1 |
| 6 | 4 | `[6,7]` | 1 |
| 8 | 5 | `[1,2,3,4,5,6,7,9,11,12,14]` | 1 |

> Ojo: el puesto **5 (Gestoría)** tiene justo las mismas estaciones que los módulos sasisopa/sgm:
> `[1,2,3,4,5,6,7,9,11,12,14]`. Por eso el flujo Gestoría → SASISOPA funciona sin fricción.

### Sentido del JSON `estaciones`

| Valor | Significado |
|---|---|
| `[1,2,3,...]` | Solo esas estaciones (lista explícita) |
| `NULL` / `'null'` | **Heredar**: sin restricción extra (usa lo que venga de arriba) |
| `'*'` o `[]` | Sin restricción (todas las del módulo) |

---

## 4 · `tb_multiestacion_usuario` — estaciones por usuario

### Qué es y para qué sirve
Excepción **por usuario** sobre el puesto. Un usuario con fila aquí NO se evalúa contra su puesto:
solo contra su propia lista.

### Columnas

| Columna | Para qué sirve |
|---|---|
| `id` | Identificador |
| `id_usuario` | El usuario (único por fila) |
| `estaciones` | Lista JSON de estaciones permitidas para ese usuario |
| `departamentos_puestos` / `departamentos_localidades` | Departamentos (según el tipo) |
| `activo` | `1` = habilitado |

### Estado real en producción
**Vacía** (0 filas): nadie tiene excepción individual por ahora.

### Prioridad entre las tablas

```
1º tb_multiestacion_usuario   → si el usuario tiene fila aquí, mandan SUS estaciones
2º tb_multiestacion_puesto    → si no, mandan las del puesto
3º sin fila en ninguna        → se usa el juego completo de estaciones soportadas del módulo
```

---

## Resumen rápido

```
MÓDULO (qué puede mostrar)     → tb_modulos_config.estaciones_soportadas
USUARIO (qué puede ver)        → tb_multiestacion_usuario  (prioridad 1)
                              → tb_multiestacion_puesto   (prioridad 2)
CATÁLOGO (qué significan los IDs) → tb_estaciones

disponibles = módulo ∩ usuario/puesto
si vacío → módulo bloqueado para el usuario
```

---

## Siguiente paso

Tablas claras. Revisá el **glosario de elementos** en `04_GLOSARIO.md`.