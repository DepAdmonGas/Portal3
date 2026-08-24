# Convenciones de Código

## Propósito

Documentar las convenciones de código identificadas en Portal3.

## Alcance

Nomenclatura, estructura de archivos, patrones de código.

---

## Nombres de clases y archivos

### **Confirmado**

| Elemento | Convención | Ejemplo |
|---|---|---|
| Controladores | PascalCase + `Controller` | `UsuarioController` |
| Servicios | PascalCase + `Service` | `AuthenticationService` |
| Modelos | PascalCase (singular) | `Usuario`, `Estacion` |
| Repositorios | PascalCase + `Repository` | `UsuarioRepository` |
| Middlewares | PascalCase + `Middleware` | `AuthMiddleware` |
| Vistas | minúsculas, guiones (`-`) | `departamento-operativo.php` |

---

## Estructura de código en Controladores

### **Inferido**

- Se usa inyección de dependencias donde es posible (a través de `BaseController` o directamente si se usa Middleware).
- Los métodos suelen llamarse `index`, `create`, `update`, `delete`, `datatable`.
- Retornan llamadas a `View::render()` o `JsonResponse::success/error()`.

---

## Archivos relevantes

- TODO

---

## Preguntas pendientes

- TODO: ¿Existen reglas de linteo automáticas (PHP_CodeSniffer, ESLint)?
- TODO: ¿Cuáles son las convenciones para nombres de variables y funciones en JS?
- TODO: ¿Se usa tipado estricto (`declare(strict_types=1);`) en todos los archivos PHP?
