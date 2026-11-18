<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titulo) ?></title>
<style>
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    width: 100%;
    background-image: url("<?= base_url() ?>/assets/img/pdf/fondo2.jpg");
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
}

.content-wrapper {
    position: relative;
    z-index: 1;
    height: 90%;
    margin: 0 auto;
    padding: 40px;
}

h2 {
    font-size: 28px;
    font-weight: bold;
    margin: 0 0 14px 0;
    overflow-wrap: break-word;
    word-wrap: break-word;
}

p {
    margin: 6px 0;
    line-height: 1.5;
    overflow-wrap: break-word;
    word-wrap: break-word;
}
hr { border: none; border-top: 1px solid #999; margin: 12px 0; }

.text-end { text-align: right; }
.text-center { text-align: center; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 20px; }

.custom-table {
    width: 100%;
    table-layout: fixed;
    font-size: .75em;
    border-collapse: collapse;
}

.custom-table thead th,
.custom-table tbody th,
.custom-table tbody td {
    padding: 10px;
    font-size: 10.5px;
    text-align: center;
    vertical-align: middle;
    border-top: 1px solid #dee2e6;
    overflow-wrap: break-word;
    word-wrap: break-word;
}

.tables-bg { background-color: #215D98; color: #ffffff; }
.contenido-table-bg { background-color: #f2f2f2; }

.custom-table thead th,
.custom-table tbody th { background-color: #215D98; color: #ffffff; font-weight: bold; }
.custom-table tbody td { background-color: #f2f2f2; }

table.custom-table thead { display: table-header-group; }

.firmas-contenedor { width: 100%; }
table.firmas { width: 100%; table-layout: fixed; border-collapse: collapse; border: 1px solid #dee2e6; }
table.firmas > tbody > tr > td.firma-col {
    padding: 0;
    vertical-align: top;
    text-align: center;
    border-left: 1px solid #dee2e6;
}

table.firma {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    page-break-inside: avoid;
}
table.firma th {
    padding: 8px;
    font-size: 10.5px;
    text-align: center;
    vertical-align: middle;
    border: 0;
    overflow-wrap: break-word;
    word-wrap: break-word;
}
table.firma thead th { background-color: #ffffff; color: #6c757d; font-weight: normal; }
table.firma tbody th { background-color: #ffffff; font-weight: bold; }

.firma img { width: 70%; max-width: 100%; max-height: 95px; object-fit: contain; }
.firma-detalle { height: 88px; font-weight: normal !important; border-bottom: 1px solid #dee2e6 !important; }
.firma-estado { font-weight: normal; }
.clearfix { clear: both; }
</style>
</head>
<body>

<div class="content-wrapper">

<h2><?= htmlspecialchars($titulo) ?></h2>

<div class="text-end mb-3">
<b>Formato:</b> <?= htmlspecialchars($detalle['codigo_formato']) ?>
<br>
<b>No. De control:</b> <?= htmlspecialchars($detalle['no_control']) ?>
<p><?= htmlspecialchars($detalle['encabezado_ciudad']) ?></p>
</div>

<div class="mb-4">
<?php $dirigido = array_values(array_filter(array_map('trim', explode('/', $detalle['dirigido_a'])))); ?>
<?php if (!empty($dirigido)): ?>
<?php foreach ($dirigido as $i => $linea): ?>
<?php if ($i === 0): ?>
<b><?= htmlspecialchars($linea) ?></b><br>
<?php else: ?>
<p><b><?= htmlspecialchars($linea) ?></b></p>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
<p><?= htmlspecialchars($detalle['intro']) ?></p>
</div>

<?php if (!empty($detalle['tabla']['headers'])): ?>
<div class="mb-4">
<table class="custom-table">
<thead class="tables-bg">
<tr>
<?php foreach ($detalle['tabla']['headers'] as $header): ?>
<th><?= htmlspecialchars($header) ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody class="contenido-table-bg">
<?php foreach ($detalle['tabla']['rows'] as $row): ?>
<tr>
<?php foreach ($row as $cell): ?>
<?php $tag = !empty($cell['header']) ? 'th' : 'td'; ?>
<<?= $tag ?> <?= !empty($cell['colspan']) ? 'colspan="' . (int)$cell['colspan'] . '"' : '' ?>><?= htmlspecialchars($cell['value']) ?></<?= $tag ?>>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<div class="text-center">
<p>Sin más por el momento quedo de usted.</p>
<hr>
</div>

<?php if (!empty($firmas)): ?>
<div class="firmas-contenedor">
<table class="firmas">
<tbody>
<tr>
<?php $firmaColumna = 100 / count($firmas); ?>
<?php foreach ($firmas as $f): ?>
<td class="firma-col" style="width: <?= $firmaColumna ?>%;">
<table class="firma">
<thead class="tables-bg">
<tr><th><?= htmlspecialchars($f['usuario_nombre'] ?? $f['nombre']) ?></th></tr>
</thead>
<tbody>
<tr>
<th class="firma-detalle">
<?php $imagenFirma = $f['firma_img_data'] ?? ''; ?>
<?php if ($imagenFirma === '' && !empty($f['firma_img_url'])): ?>
<?php $imagenFirma = base_url() . $f['firma_img_url']; ?>
<?php endif; ?>
<?php if ($imagenFirma !== ''): ?>
<img src="<?= htmlspecialchars($imagenFirma, ENT_QUOTES, 'UTF-8') ?>">
<?php elseif (in_array($f['tipo_firma'], ['B', 'C'], true)): ?>
<?php if (!empty($f['fecha_pdf']) && $f['fecha_pdf'] !== 'Fecha no disponible'): ?>
<div class="firma-estado">La solicitud se firmó por un medio electrónico.</div>
<div><b>Fecha: <?= htmlspecialchars($f['fecha_pdf']) ?></b></div>
<?php elseif (!empty($f['firma_texto'])): ?>
<?= $f['firma_texto'] ?>
<?php else: ?>
<div class="firma-estado">La solicitud se firmó por un medio electrónico.</div>
<div><b>Fecha: <?= htmlspecialchars($f['fecha_pdf'] ?? 'Fecha no disponible') ?></b></div>
<?php endif; ?>
<?php elseif (in_array($f['tipo_firma'], ['A', 'D'], true)): ?>
<div class="firma-estado">La firma está registrada, pero su imagen no está disponible.</div>
<?php endif; ?>
</th>
</tr>
<tr>
<th><?= htmlspecialchars($f['tipo_label'] ?? 'Firma ' . $f['tipo_firma']) ?></th>
</tr>
</tbody>
</table>
</td>
<?php endforeach; ?>
</tr>
</tbody>
</table>
<div class="clearfix"></div>
</div>
<?php endif; ?>

</div>

</body>
</html>
