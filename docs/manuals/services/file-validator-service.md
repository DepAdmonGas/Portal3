# Documentación: `FileValidatorService`

El servicio `FileValidatorService` proporciona una capa robusta para validar la integridad, el tipo MIME real y la seguridad de los archivos subidos al servidor mediante peticiones HTTP.

A diferencia de las comprobaciones convencionales basadas únicamente en la extensión declarada del archivo (ej. `.png` o `.pdf`), este servicio realiza una inspección profunda a nivel de **Magic Bytes** (encabezado binario real) y analiza el contenido interno según el tipo de archivo procesado.

---

## 🛡️ Pilares de Seguridad

1. **Validación por Magic Bytes (`finfo`):** Determina el MIME type leyendo los primeros bytes del archivo. Esto evita ataques en los que un archivo ejecutable malicioso es renombrado para simular ser una imagen (ej. cambiar `malware.exe` a `foto.jpg`).
2. **Mitigación de XSS en SVG:** Analiza el contenido XML de archivos `.svg` utilizando expresiones regulares y `DOMDocument` para detectar e impedir la inyección de código JavaScript (`<script>`, eventos `onload=`, URIs `javascript:`, etc.).
3. **Protección contra Image Bombs / Pixel Floods:** Evalúa las dimensiones reales (ancho, alto y megapíxeles totales) en imágenes de mapa de bits para prevenir ataques de Denegación de Servicio (DoS) por agotamiento de memoria RAM en el motor gráfico (GD/Imagick).
4. **Prevención de Zip Bombs:** Garantiza la firma del contenedor comprimido y promueve un control estricto de límites en bytes para evitar la subida de archivos hipercomprimidos diseñados para colapsar el almacenamiento del servidor durante su extracción.

---

## 📚 Especificación de Métodos

### `isValidMimeType(string $tmpPath, array $allowedMimes): bool`
Compara el tipo MIME real del archivo analizado con la lista de tipos permitidos.

- **`$tmpPath`**: Ruta temporal del archivo en el servidor (ej. `$_FILES['archivo']['tmp_name']`).
- **`$allowedMimes`**: Arreglo con la lista blanca de tipos MIME aceptados.

---

### `isValidImageIntegrity(string $tmpPath, int $maxWidth = 4096, int $maxHeight = 4096, int $maxMegapixels = 12): bool`
Comprueba si la imagen de mapa de bits (JPG, PNG, WEBP, GIF) se puede decodificar correctamente y valida que no supere los umbrales de resolución especificados.

- **`$maxWidth`**: Ancho máximo permitido en píxeles.
- **`$maxHeight`**: Alto máximo permitido en píxeles.
- **`$maxMegapixels`**: Límite global de resolución `(ancho * alto) / 1_000_000`.

---

### `isSafeSvg(string $tmpPath): bool`
Asegura que la estructura XML de un archivo SVG esté bien formada y libre de código ejecutable o referencias peligrosas.

- **Validaciones aplicadas:**
  - Bloqueo de etiquetas `<script>`, URIs `javascript:`, data-URIs HTML y bloques `<foreignObject>`.
  - Detección de atributos de eventos inline (`onload=`, `onclick=`, `onerror=`, etc.).
  - Desactivación de resolución de entidades externas XML (`<!ENTITY`) para prevenir ataques **XXE**.

---

### `validateImage(string $tmpPath, array $allowedMimes, int $maxSizeBytes = 5242880): bool`
Método de alto nivel para gestionar la validación integral de cualquier imagen (Raster o SVG). Ejecuta en secuencia:
1. Verificación de existencia del archivo en disco y peso en bytes.
2. Comprobación del tipo MIME real con `finfo`.
3. Verificación de seguridad en SVG o validación de integridad gráfica para mapas de bits.

---

## 💻 Ejemplos de Integración

### Caso 1: Validación de Archivo PDF

```php
    $validator = new FileValidatorService();
    if (!$validator->isValidMimeType($_FILES['archivo']['tmp_name'], ['application/pdf'])) {
        JsonResponse::error(
            'El tipo de archivo no es válido o está corrupto. Solo se permiten PDF.'
        );
        exit;
    }
```
---

### Caso 2: Validación de Imágenes (Raster + SVG)

```php
use App\Services\FileValidatorService;

$validator = new FileValidatorService();
$tmpFile =$_FILES['avatar']['tmp_name'] ?? '';

// Validar imagen con un límite de 5 MB
$isValid =$validator->validateImage(
    tmpPath: $tmpFile,
    allowedMimes: ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
    maxSizeBytes: 5 * 1024 * 1024
);

if (!$isValid) {
    JsonResponse::error('La imagen no es válida, excede las dimensiones permitidas o contiene un riesgo de seguridad.', 422);
    return;
}
```
---

### Caso 3: Validación de Documentos (PDF, Word, Excel)

```php
use App\Services\FileValidatorService;

$validator = new FileValidatorService();
$tmpFile =$_FILES['documento']['tmp_name'] ?? '';

$allowedMimes = [
    'application/pdf',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'application/vnd.ms-excel', // .xls
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' // .xlsx
];

// 1. Validar el tipo MIME mediante Magic Bytes
if (!$validator->isValidMimeType($tmpFile,$allowedMimes)) {
    JsonResponse::error('El formato del documento no está permitido.', 422);
    return;
}

// 2. Control del peso máximo del archivo (ej. 10 MB)
if (filesize($tmpFile) > 10 * 1024 * 1024) {
    JsonResponse::error('El documento supera el límite de 10 MB.', 422);
    return;
}
```
---

### Caso 4: Validación de Archivos Comprimidos (ZIP, RAR, 7Z)

```php
use App\Services\FileValidatorService;

$validator = new FileValidatorService();
$tmpFile =$_FILES['paquete']['tmp_name'] ?? '';

$allowedArchiveMimes = [
    'application/zip',
    'application/x-zip-compressed',
    'application/vnd.rar',
    'application/x-rar-compressed',
    'application/x-7z-compressed'
];

// 1. Verificar firma real del contenedor comprimido
if (!$validator->isValidMimeType($tmpFile,$allowedArchiveMimes)) {
    JsonResponse::error('El archivo comprimido no es válido o está dañado.', 422);
    return;
}

// 2. Control estricto del peso (Límite 20 MB para prevenir Zip Bombs)
if (filesize($tmpFile) > 20 * 1024 * 1024) {
    JsonResponse::error('El paquete comprimido supera el tamaño máximo de 20 MB.', 422);
    return;
}
```

---