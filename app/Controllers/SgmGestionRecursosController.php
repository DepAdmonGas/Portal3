<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Helpers\ImageHelper;
use App\Services\ModuloService;

use App\Models\Usuario;
use App\Models\Sgm\Responsable;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmGestionRecursosController extends BaseController
{
    protected string $modulo = 'sgm';

    public function index()
    {
        $title = '6. Gestion de los Recursos';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $usuarios = Usuario::where('id_gas', $this->estacionId())
            ->where('estatus', 0)
            ->orderBy('nombre')
            ->get();

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'usuarios' => $usuarios,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/sgm/gestion-recursos/index.actions.init.js?v=1.1.0'
            ],
            'help' => true
        ];

        View::render('sgm/gestion-recursos/index', $data, 'sgm');
    }

    public function table()
    {

        $data = Responsable::where(
            'id_estacion',
            $this->estacionId()
        )
            ->orderByDesc('fecha')
            ->get()
            ->map(function ($item) {

                return [
                    'id' => $item->id,
                    'fecha' => formatearFecha($item->fecha)
                ];
            });


        JsonResponse::custom([
            'data' => $data
        ]);
    }

    public function create()
    {
        $fecha = trim((string) Request::jsonInput('fecha'));
        $responsable = (int) Request::jsonInput('responsable');
        $auxiliar = (int) Request::jsonInput('auxiliar');

        if (empty($fecha) && $responsable <= 0 && $auxiliar <= 0) {
            JsonResponse::error(
                'Complete los campos obligatorios.'
            );
            return;
        }

        Responsable::create([
            'id_estacion' => $this->estacionId(),
            'fecha'        => $fecha,
            'responsable'  => $responsable,
            'auxiliar'     => $auxiliar,
        ]);

        JsonResponse::success('Designación de responsable agregada.');
    }

    public function delete()
    {
        $id = (int) Request::jsonInput('id');

        if ($id <= 0) {
            JsonResponse::error('El identificador es inválido.');
            return;
        }

        $responsable = Responsable::where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->first();

        if (!$responsable) {
            JsonResponse::error('La designación de responsable no existe.');
            return;
        }

        $responsable->delete();

        JsonResponse::success('Designación de responsable eliminada.');
    }

    public function pdf(int $id)
    {
        $registro = Responsable::with([
            'usuarioResponsable',
            'usuarioAuxiliar',
            'estacion'
        ])
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->first();

        if (!$registro) {
            JsonResponse::error('No se encontró el registro.');
            return;
        }

        $realizadoPor = Usuario::select('tb_usuarios.nombre')
            ->join('sgm_autorizado', 'sgm_autorizado.id_usuario', '=', 'tb_usuarios.id')
            ->where('tb_usuarios.id_gas', $this->estacionId())
            ->where('sgm_autorizado.estado', 1)
            ->value('nombre');

        $firmaResponsable =
            ImageHelper::firmaPath($registro->usuarioResponsable?->firma);

        $firmaAuxiliar =
            ImageHelper::firmaPath($registro->usuarioAuxiliar?->firma);

        $firmaRepresentante =
            ImageHelper::firmaPath($registro->estacion->firma);

        $fecha = formatearFecha($registro->fecha);

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '

        <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Designación de responsable SGM</title>
        <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">
        <style>
            ' . $css . '
        </style>
        </head>
        <body>

    <table class="table table-bordered">
        <tr>
            <td align="center" rowspan="2">
                ' . $registro->estacion->razonsocial . '
            </td>

            <td align="center" rowspan="2">
                <b>Designación de responsable SGM</b>
            </td>

            <td align="center">
                <b>Fecha de autorización: 01-01-2024</b>
            </td>
        </tr>

        <tr>
            <td align="center">
                Fo.SGM.007
            </td>
        </tr>

        <tr>
            <td align="center">
                Realizado por:<br>
                ' . $realizadoPor . '
            </td>

            <td align="center">
                Revisado por:<br>
                Eduardo Galicia Flores
            </td>

            <td align="center">
                Autorizado por:<br>
                ' . $registro->estacion->apoderado_legal . '
            </td>
        </tr>

    </table>

<br>

<p align="right">
' . $registro->estacion->di_municipio . ',
' . $registro->estacion->di_estado . '
a ' . $fecha . '
</p>

<p>
A QUIEN CORRESPONDA<br>
COMISIÓN REGULADORA DE ENERGÍA<br>
PRESENTE
</p>

<p>

<b>' . $registro->estacion->apoderado_legal . '</b>

en carácter de representante legal de la estación

<b>' . $registro->estacion->razonsocial . '</b>

con domicilio en

<b>' . $registro->estacion->direccioncompleta . '</b>.

</p>

<p>

Sírvase la presente para designar la persona que será responsable de la implementación y adecuada operación del Sistema de Gestión de Medición, así como al personal especializado que auxiliará en dichas tareas.

</p>

<p>
Quienes tendrán, entre otras, las siguientes responsabilidades:
</p>

<ol>

<li>
Asegurar que las actividades del SGM se apeguen a los procedimientos correspondientes.
</li>

<li>
Elaborar los reportes e información requerida por la Comisión o por la Empresa Especializada durante visitas de verificación.
</li>

<li>
Conservar la documentación relativa al SGM por un periodo mínimo de diez años.
</li>

<li>
Generar, organizar, implementar cambios, difundir, almacenar y dar trazabilidad a toda la información derivada de la operación del SGM.
</li>

</ol>

<p>

La designación del grupo de personas se realizó por así convenir a mi representada, eligiendo personal relacionado directamente con la operación de la empresa.

</p>

<br><br>

<table width="100%">

<tr>

<td align="center" width="50%">

<b>
Nombre y firma de conformidad del responsable de implementación del Sistema de Gestión de Medición
</b>

<br><br>

<img src="' . $firmaResponsable . '" width="120">

<br>

' . $registro->usuarioAuxiliar->nombre . '

</td>

<td align="center" width="50%">

<b>
Personal especializado que auxiliará en las tareas de implementación del Sistema de Gestión de Medición
</b>

<br><br>

<img src="' . $firmaAuxiliar . '" width="120">

<br>

' . $registro->usuarioAuxiliar->nombre . '

</td>

</tr>

</table>

<br><br><br>

<table width="100%">

<tr>

<td align="center">

<b>
Representante legal
</b>

<br><br>

<img src="' . $firmaRepresentante . '" width="120">

<br>

' . $registro->estacion->apoderado_legal . '

</td>

</tr>

</table>
</body>
</html>
';

        $options = new Options();
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);
        $options->set('defaultFont', 'Arial');

        $options->setChroot(ROOT_PATH);

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream(
            'Designación de responsable SGM.pdf',
            ['Attachment' => false]
        );
    }
}
