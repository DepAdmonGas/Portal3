# Validación

## Propósito

Documentar los mecanismos de validación de datos en Portal3.

## Alcance

Validación de input, helpers de validación, librerías usadas.

---

## Mecanismos de validación identificados

### **Confirmado**

Portal3 tiene múltiples mecanismos de validación:

1. **Helper global `validate_input()`** — en `app/Helpers/helpers.php`
2. **Helper global `sanitize_input()`** — en `app/Helpers/helpers.php`
3. **`respect/validation`** — librería declarada en composer.json
4. **`PasswordValidator`** — validación específica de contraseñas (`app/Core/PasswordValidator.php`)
5. **Validación directa en controladores/servicios** — `empty()`, `filter_var()`, etc.

---

## `validate_input($data, $rules)` (confirmado)

Reglas disponibles:

| Regla | Descripción |
|---|---|
| `required` | Campo no puede estar vacío |
| `email` | Debe ser email válido |
| `numeric` | Debe ser numérico |
| `min:N` | Valor mínimo numérico |
| `max:N` | Longitud máxima de string |

Uso: `validate_input($data, ['campo' => 'required|email|max:255'])`

---

## `sanitize_input($value, $type)` (confirmado)

Tipos disponibles:

| Tipo | Método |
|---|---|
| `string` | `trim()` + `htmlspecialchars()` |
| `int` / `integer` | `FILTER_SANITIZE_NUMBER_INT` |
| `float` / `decimal` | `FILTER_SANITIZE_NUMBER_FLOAT` |
| `email` | `FILTER_SANITIZE_EMAIL` |
| `url` | `FILTER_SANITIZE_URL` |
| `alphanumeric` | `preg_replace` (solo alfanumérico) |
| `uuid` | Valida formato UUID |

---

## PasswordValidator (confirmado)

- Archivo considerable: `app/Core/PasswordValidator.php` (7093 bytes)
- TODO: Documentar las reglas de complejidad de contraseña implementadas

---

## `respect/validation` (inferido)

La librería está declarada en `composer.json` pero su uso real en el código requiere verificación.

- TODO: Confirmar si `respect/validation` se usa en el código actualmente

---

## Inconsistencias potenciales (inferido)

Con múltiples mecanismos de validación coexistiendo, existe riesgo de:
- Validación inconsistente entre controladores
- Duplicación de lógica de validación
- Ausencia de validación en algunos endpoints

---

## Archivos relevantes

- `app/Helpers/helpers.php` — `validate_input`, `sanitize_input`
- `app/Core/PasswordValidator.php` — Validación de contraseñas
- `app/Core/PasswordService.php` — Hashing de contraseñas

---

## Preguntas pendientes

- TODO: ¿Se usa `respect/validation` actualmente en el código?
- TODO: ¿Existe validación consistente en todos los endpoints POST?
- TODO: ¿Qué reglas de contraseña implementa `PasswordValidator`?
- TODO: ¿Se valida el tipo de archivo en uploads?
