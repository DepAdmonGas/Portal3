# MULTIESTACIÓN EN PORTAL 3 · INICIO

> Leé este archivo primero. Es la puerta de entrada del paquete **V3**, una documentación
> **sencilla y completa** para entender qué es la multiestación y cómo implementarla.

---

## 1 · Qué es la multiestación

Portal 3 trabaja con datos de **estaciones** (gasolineras): Interlomas, Palo Solo, Valle de
Guadalupe, etc.

La **multiestación** es la capacidad de que **cada módulo** (SASISOPA, SGM, Gestoría...) tenga
**su propia estación seleccionada**, y que el sistema:

1. Muestre un **menú** para que el usuario elija la estación.
2. **Recuerde** la elección mientras el usuario navega (la guarda en la sesión).
3. **Filtre todos los datos** del módulo por esa estación.

> **En una frase:** *"El usuario elige estación → el sistema lo recuerda en la sesión → cada
> pantalla del módulo filtra por esa estación."*

**Analogía:** es como cambiar de sucursal en una app de banco. Elegís "Interlomas" → ves lo de
Interlomas; elegís "Palo Solo" → todo pasa a Palo Solo.

---

## 2 · Los 4 conceptos que hay que entender (el resto está en el glosario)

| Concepto | Qué es | Para qué sirve |
|---|---|---|
| **moduleKey** | El nombre único del módulo (`'sasisopa'`, `'sgm'`) | Para conectar BD, Controller, Vista y JS **con el mismo identificador** |
| **Contexto** | La estación elegida en este momento, guardada en la sesión | Para que **todas las pantallas del módulo** sepan qué estación mostrar |
| **Selector** | El menú `<select>` para cambiar de estación | Para que el **usuario** pueda elegir |
| **Badge** | El chip de color con la estación actual | Para que el usuario vea **dónde está parado** |

---

## 3 · Orden de lectura de los documentos

Leelos **en este orden** (el paquete está pensado para ir de lo general a lo específico):

| Nº | Archivo | Qué vas a hacer/entender |
|---|---|---|
| **1** | `01_INICIO.md` (éste) | El objetivo y el mapa de lectura |
| **2** | `02_IMPLEMENTACION_PASO_A_PASO.md` | **Cómo se implementa**, de principio a fin, con cada elemento y su propósito |
| **3** | `03_BASE_DE_DATOS_Y_TABLAS.md` | Las **tablas** del sistema: qué guarda cada una y los valores reales |
| **4** | `04_GLOSARIO.md` | **Glosario completo**: cada elemento explicado y para qué sirve |
| **5** | `05_EJEMPLOS_SASISOPA_SGM_GESTORIA.md` | Los **5 ejemplos** reales: 3 desde JavaScript (selector/gestoría) y 2 desde el Controller (URL/rol) |

> Los ejemplos van **casi al final** a propósito: primero aprendé a implementar y después mirá
> cómo lo hacen los módulos que ya están hechos, tanto vía **JavaScript** como vía **Controller**.

---

## Siguiente paso

Seguí con **`02_IMPLEMENTACION_PASO_A_PASO.md`** (el "cómo se implementa").