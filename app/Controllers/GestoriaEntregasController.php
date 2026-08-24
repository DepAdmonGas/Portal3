<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;
use App\Services\FileValidatorService;

use App\Models\Usuario;
use App\Models\Estacion;
use App\Models\Entregas;
use App\Models\EntregasDocumentos;
use App\Models\EntregasFinalizar;
use Carbon\Carbon;

use Dompdf\Dompdf;
use Dompdf\Options;

class GestoriaEntregasController extends BaseController
{
    protected string $modulo = 'gestoria';

    public function index()
    {
        $title = 'Entregas';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Gestoria', '/gestoria');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $estaciones = Estacion::where('numlista', '<=', 8)
            ->orderBy('numlista', 'asc')
            ->get();

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estaciones' => $estaciones,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css',
                '/css/select2-modal.css?v=1.0'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/gestoria/entregas/index.actions.init.js?v=1.3.0',
                '/js/gestoria/entregas/index.datatable.init.js?v=1.5.0',
            ],
            'help' => false
        ];

        View::render('gestoria/entregas', $data, 'main');
    }

    public function table()
    {
        $entregas = Entregas::query()
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get([
                'id',
                'fecha',
                'estacion',
                'destinatario',
                'estatus',
            ]);

        $data = $entregas->map(function ($entrega, $index) {

            return [
                'numero' => $index + 1,

                'id' => $entrega->id,

                'fecha' => $entrega->fecha?->format('Y-m-d'),

                'fecha_formateada' => $entrega->fecha
                    ? $entrega->fecha->format('d/m/Y')
                    : 'S/I',

                'estacion' => $entrega->estacion ?? 'S/I',

                'destinatario' => $entrega->destinatario,

                'estatus' => $entrega->estatus,

                'color' => match ($entrega->estatus) {
                    0 => 'text-bg-danger',
                    1 => 'text-bg-warning',
                    2 => 'text-bg-success',
                    default => '',
                },

                'puede_editar' => true,

                'puede_eliminar' => $entrega->estatus !== 2,
            ];
        })->values();

        JsonResponse::custom([
            'data' => $data
        ], 200);
    }

    public function create(): void
    {
        $entrega = Entregas::create([
            'estacion'    => Request::jsonInput('estacion'),
            'fecha'       => date('Y-m-d'),
            'destinatario' => Request::jsonInput('destinatario'),
            'estatus'     => 0,
        ]);

        JsonResponse::success('Entrega registrada correctamente.', [
            'id' => $entrega->id
        ]);
    }

    public function delete(): void
    {
        try {
            $id = Request::jsonInput('id');

            $eliminado = Entregas::where('id', $id)->delete();

            if (!$eliminado) {
                JsonResponse::error('No se encontró la entrega.');
                return;
            }

            JsonResponse::success('Entrega eliminada correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('No fue posible eliminar la entrega.');
        }
    }


    function pdf(int $id): void
    {
        $entrega = Entregas::findOrFail($id);

        $estacion = Estacion::where(
            'razonsocial',
            $entrega->estacion
        )->first();

        $direccion = $estacion?->direccioncompleta ?? '';

        $finalizar = EntregasFinalizar::where(
            'id_entrega',
            $id
        )->first();

        $fechaEntrega = '';
        $horaEntrega = '';
        $nombre = '';

        if ($finalizar) {

            $fechaHora = $finalizar->fecha;

            if ($fechaHora) {

                $fechaHora = \Carbon\Carbon::parse($fechaHora);

                $fechaEntrega = formatearFecha(
                    $fechaHora->format('Y-m-d')
                );

                $horaEntrega = $fechaHora->format('g:i a');
            }

            $nombre = $finalizar->nombre ?? '';
        }

        $documentos = EntregasDocumentos::where(
            'id_entrega',
            $id
        )
            ->orderBy('id')
            ->get();

        $cantidadEstaciones = EntregasDocumentos::where(
            'id_entrega',
            $id
        )
            ->select('id_estacion')
            ->groupBy('id_estacion')
            ->count();

        $contenido = '';

        $css = file_get_contents(
            ROOT_PATH . '/public/assets/css/pdf.css'
        );

        $rutaLogo = dirname(__DIR__, 2) . '/public/assets/images/logos/Logo.png';

        if (!file_exists($rutaLogo)) {
            throw new \RuntimeException(
                'No se encontró el logo: ' . $rutaLogo
            );
        }

        $dataLogo = file_get_contents($rutaLogo);

        if ($dataLogo === false) {
            throw new \RuntimeException(
                'No fue posible leer el logo.'
            );
        }

        $baseLogo = 'data:image/png;base64,' . base64_encode($dataLogo);

        $contenido .= '<!DOCTYPE html>';
        $contenido .= '<html>';
        $contenido .= '<head>';
        $contenido .= '<meta charset="UTF-8">';
        $contenido .= '<title>Formato entregas</title>
         <style>' . $css . '</style>
        ';
        $contenido .= '</head>';
        $contenido .= '<body class="p-3">';

        $contenido .= '<img src="' . $baseLogo . '" style="width: 150px;">';

        $contenido .= '<div class="text-right mt-3">';
        $contenido .= 'Huixquilucan, Estado de México a ' .
            formatearFecha($entrega->fecha);
        $contenido .= '</div>';

        $contenido .= '<div class="text-right">';
        $contenido .= '<b>Asunto:</b> Entrega de documentos';
        $contenido .= '</div>';


        $contenido .= '<div class="mt-3"><br><br></div>';


        $contenido .= '<div class="mt-3">';

        $contenido .= '<b>' .
            $entrega->destinatario .
            '</b>';

        $contenido .= '</div>';


        $contenido .= '<div>';

        $contenido .= $entrega->estacion;

        $contenido .= '</div>';


        $contenido .= '<div>';

        $contenido .= $direccion;

        $contenido .= '</div>';

        $contenido .= '<div class="mt-3"></div>';


        $contenido .= '<div class="mt-3">';

        $contenido .= 'P r e s e n t e.';

        $contenido .= '</div>';

        $contenido .= '<div class="mt-3"></div>';

        $contenido .= '<div class="mt-3">';

        $contenido .= 'Se hace entrega de la siguiente documentación:';

        $contenido .= '</div>';


        $contenido .= '<div class="mt-3"></div>';

        $contenido .= '<table class="table table-sm table-bordered">';

        $contenido .= '<thead>';

        $contenido .= '<tr>';

        $contenido .= '<th>No.</th>';

        if ($cantidadEstaciones > 1) {

            $contenido .= '<th>Razón Social</th>';
        }

        $contenido .= '<th>Nombre del documento</th>';

        $contenido .= '<th>Fecha del oficio</th>';

        $contenido .= '<th>Original y/o copia</th>';

        $contenido .= '</tr>';

        $contenido .= '</thead>';

        $contenido .= '<tbody>';


        $numero = 1;

        foreach ($documentos as $documento) {

            $razonSocial = '';

            if ((int) $documento->id_estacion !== 0) {

                $estacionDocumento = Estacion::find(
                    $documento->id_estacion
                );

                $razonSocial =
                    $estacionDocumento?->razonsocial ?? '';
            }


            $contenido .= '<tr>';

            $contenido .= '<td class="text-center">';

            $contenido .= '<b>' .
                $numero .
                '</b>';

            $contenido .= '</td>';


            if ($cantidadEstaciones > 1) {

                $contenido .= '<td class="text-center">';

                $contenido .= $razonSocial;

                $contenido .= '</td>';
            }


            $contenido .= '<td class="text-center">';

            $contenido .= $documento->documento;

            $contenido .= '</td>';


            $contenido .= '<td class="text-center">';

            $contenido .= formatearFecha(
                $documento->fecha
            );

            $contenido .= '</td>';


            $contenido .= '<td class="text-center">';

            $contenido .= $documento->detalle;

            $contenido .= '</td>';


            $contenido .= '</tr>';


            $numero++;
        }


        $contenido .= '</tbody>';

        $contenido .= '</table>';

        $contenido .= '<div class="mt-3"></div>';

        $contenido .= '<div class="mt-3">';

        $contenido .= '<b>';

        $contenido .= 'Nota: Es importante mencionar que estos documentos deben estar bien archivados en la estación de servicio.';

        $contenido .= '</b>';

        $contenido .= '</div>';


        $contenido .= '<div class="mt-3"></div>';

        $contenido .= '<div class="mt-3">';

        $contenido .= 'Recibido';

        $contenido .= '</div>';


        $contenido .= '<div>';

        $contenido .= '<b>Nombre:</b> ' .
            $nombre;

        $contenido .= '</div>';


        $contenido .= '<div>';

        $contenido .= '<b>Fecha:</b> ' .
            $fechaEntrega .
            ' ' .
            $horaEntrega;

        $contenido .= '</div>';


        $contenido .= '</body>';

        $contenido .= '</html>';


        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($contenido);

        $dompdf->setPaper(
            'A4',
            'portrait'
        );

        $dompdf->render();

        $dompdf->stream(
            'Formato entregas.pdf',
            [
                'Attachment' => true
            ]
        );
    }

    //---------------------------------------------------------------

    public function formularioEntregas(int $id)
    {
        $title = 'Formulario de entregas';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Gestoria', '/gestoria');
        Breadcrumb::add('Entregas', '/gestoria/entregas');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);


        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'links' => [
                '/libs/select2/dist/css/select2.min.css',
                '/css/select2-modal.css?v=1.0'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/gestoria/entregas/formulario.actions.init.js?v=1.7.0',
            ],
            'help' => false
        ];

        View::render('gestoria/entregas-formulario', $data, 'main');
    }

    public function formularioData(int $id): void
    {
        if (!$id) {
            JsonResponse::error('ID de entrega no proporcionado.');
            return;
        }

        $entrega = Entregas::find($id);

        if (!$entrega) {
            JsonResponse::error('La entrega no existe.');
            return;
        }

        $documentos = EntregasDocumentos::where('id_entrega', $id)
            ->orderBy('id')
            ->get();

        $idsEstaciones = $documentos
            ->where('id_estacion', '!=', 0)
            ->pluck('id_estacion')
            ->unique()
            ->values();

        $estacionesDocumentos = Estacion::whereIn('id', $idsEstaciones)
            ->get([
                'id',
                'razonsocial',
                'direccioncompleta',
            ])
            ->keyBy('id');

        $estaciones = Estacion::where('numlista', '<=', 8)
            ->orderBy('numlista', 'asc')
            ->get([
                'id',
                'razonsocial',
                'direccioncompleta',
            ]);

        $documentosData = $documentos->map(function ($documento) use ($estacionesDocumentos) {

            $razonSocial = 'S/I';

            if (
                $documento->id_estacion != 0 &&
                isset($estacionesDocumentos[$documento->id_estacion])
            ) {
                $razonSocial =
                    $estacionesDocumentos[$documento->id_estacion]->razonsocial;
            }

            return [
                'id'          => $documento->id,
                'id_entrega'  => $documento->id_entrega,
                'id_estacion' => $documento->id_estacion,
                'razonsocial' => $razonSocial,
                'documento'   => $documento->documento,
                'fecha'       => formatearFecha($documento->fecha->format('Y-m-d')),
                'detalle'     => $documento->detalle,
                'archivo'     => $documento->archivo,
            ];
        })->values();


        JsonResponse::custom([
            'entrega' => [
                'id'           => $entrega->id,
                'estacion'     => $entrega->estacion,
                'destinatario' => $entrega->destinatario,
                'fecha'        => $entrega->fecha->format('Y-m-d'),
                'estatus'      => $entrega->estatus,
            ],

            'documentos' => $documentosData,

            'estaciones' => $estaciones,
        ]);
    }

    public function createDocumento(int $id)
    {

        $idEstacion = (int) Request::jsonInput('id_estacion') ?? 0;

        try {

            $documento = EntregasDocumentos::create([
                'id_entrega'  => $id,
                'id_estacion' => $idEstacion,
                'documento'   => Request::jsonInput('documento'),
                'fecha'       => Request::jsonInput('fecha'),
                'detalle'     => Request::jsonInput('detalle'),
                'archivo'     => '',
            ]);

            JsonResponse::success('Documento agregado correctamente.', [
                'id' => $documento->id
            ]);
        } catch (\Throwable $e) {
            JsonResponse::error('No se agrego documento');
        }
    }

    public function deleteDocumento(int $idEntrega)
    {
        try {
            $idDocumento = Request::jsonInput('id');

            $eliminado = EntregasDocumentos::where('id_entrega', $idEntrega)
                ->where('id', $idDocumento)
                ->delete();

            if (!$eliminado) {
                JsonResponse::error('No se encontró el documento.');
                return;
            }

            JsonResponse::success('Documento eliminada correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('No fue posible eliminar el documento.');
        }
    }

    public function createAcuse(int $id): void
    {
        try {

            $idDocumento = Request::input('id');

            if (
                !$idDocumento ||
                !isset($_FILES['acuse'])
            ) {

                JsonResponse::error(
                    'Debe seleccionar una imagen.'
                );

                return;
            }


            $documento = EntregasDocumentos::findOrFail(
                $idDocumento
            );


            $file = $_FILES['acuse'];

            $validator = new FileValidatorService();

            if (
                !$validator->isValidMimeType(
                    $file['tmp_name'],
                    [
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp'
                    ]
                )
            ) {

                JsonResponse::error(
                    'El archivo no es una imagen válida. Solo se permiten JPG, PNG, GIF o WEBP.'
                );

                return;
            }

            $extension = strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );

            $nombreArchivo = sprintf(
                'ACUSE-%d-%d.%s',
                $documento->id,
                time(),
                $extension
            );

            $carpeta = dirname(__DIR__, 2) .
                '/public/uploads/archivos/entregas/';


            if (!is_dir($carpeta)) {

                mkdir(
                    $carpeta,
                    0777,
                    true
                );
            }

            $destino = $carpeta .
                $nombreArchivo;

            if (
                !move_uploaded_file(
                    $file['tmp_name'],
                    $destino
                )
            ) {

                JsonResponse::error(
                    'No fue posible guardar la imagen.'
                );

                return;
            }

            $documento->update([
                'archivo' => $nombreArchivo
            ]);


            JsonResponse::success(
                'Acuse agregado correctamente.',
                [
                    'archivo' => $nombreArchivo,

                    'url' =>
                    '/uploads/archivos/entregas/' .
                        $nombreArchivo
                ]
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                $e
            );
        }
    }

    public function finalizarFormulario(int $id): void
    {
        try {

            if (!$id) {

                JsonResponse::error(
                    'ID de entrega no proporcionado.'
                );

                return;
            }

            $entrega = Entregas::find($id);

            if (!$entrega) {

                JsonResponse::error(
                    'La entrega no existe.'
                );

                return;
            }

            $entrega->update([
                'estacion'     => Request::input('estacion'),
                'fecha'        => Request::input('fecha'),
                'destinatario' => Request::input('destinatario'),
                'estatus'      => 1,
            ]);

            JsonResponse::success(
                'Entrega finalizada correctamente.'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                $e->getMessage()
            );
        }
    }

    public function finalizarEntrega(int $id): void
    {
        try {

            $nombre = Request::input('nombre');

            if (!$id) {
                JsonResponse::error(
                    'ID de entrega no proporcionado.'
                );

                return;
            }

            $entrega = Entregas::find($id);

            if (!$entrega) {
                JsonResponse::error(
                    'La entrega no existe.'
                );

                return;
            }


            $estacion = Estacion::where(
                'razonsocial',
                $entrega->estacion
            )->first();

            if (!$estacion) {
                JsonResponse::error(
                    'No se encontró la estación de la entrega.'
                );

                return;
            }

            EntregasFinalizar::create([
                'id_entrega' => $entrega->id,
                'nombre'     => $nombre,
            ]);

            $entrega->update([
                'estatus' => 2,
            ]);

            $this->enviarEmail(
                $entrega->id,
                $estacion->email
            );

            JsonResponse::success(
                'Entrega finalizada correctamente.',
                [
                    'id' => $entrega->id
                ]
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                $e->getMessage()
            );
        }
    }

    private function enviarEmail(
        int $id,
        ?string $email
    ): int {

        if (!$email) {
            return 0;
        }

        $subject = 'Entregas AdmonGas';

        $msg = $this->detalle($id);

        $from = $email;

        $headers = "From: entregas@admongas.com.mx\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";

        if (mail($from, $subject, $msg, $headers)) {
            return 1;
        }

        return 0;
    }

    private function detalle(int $id): string
    {

        $entrega = Entregas::findOrFail($id);

        $estacion = Estacion::where(
            'razonsocial',
            $entrega->estacion
        )->first();

        $razonsocial = $estacion?->razonsocial ?? 'S/I';
        $direccion   = $estacion?->direccioncompleta ?? '';

        $entregaFinalizada = EntregasFinalizar::where(
            'id_entrega',
            $id
        )
            ->latest('id')
            ->first();

        $nombre = $entregaFinalizada?->nombre ?? '';

        $fechaE = '';
        $horaE = '';

        if ($entregaFinalizada?->fecha) {

            $fechaHora = $entregaFinalizada->fecha;

            if ($fechaHora instanceof \DateTimeInterface) {

                $fechaE = $fechaHora->format('Y-m-d');
                $horaE = $fechaHora->format('H:i:s');
            } else {

                $fechaHora = (string) $fechaHora;

                $partes = explode(' ', $fechaHora);

                $fechaE = $partes[0] ?? '';
                $horaE = $partes[1] ?? '';
            }
        }

        $documentos = EntregasDocumentos::where(
            'id_entrega',
            $id
        )
            ->orderBy('id')
            ->get();

        $cantidadEstaciones = $documentos
            ->where('id_estacion', '!=', 0)
            ->pluck('id_estacion')
            ->unique()
            ->count();

        $idsEstaciones = $documentos
            ->where('id_estacion', '!=', 0)
            ->pluck('id_estacion')
            ->unique()
            ->values();

        $estaciones = Estacion::whereIn(
            'id',
            $idsEstaciones
        )
            ->get()
            ->keyBy('id');

        $contenido = '';

        $RutaLogo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $contenido .=
            '<img src="' . $RutaLogo . '" style="width: 180px;">';

        $contenido .=
            '<div style="text-align: right;margin-top: 20px;">' .
            'Huixquilucan, Estado de México a ' .
            formatearFecha($entrega->fecha->format('Y-m-d')) .
            '</div>';

        $contenido .=
            '<div style="text-align: right;">' .
            '<b>Asunto:</b> Entrega de documentos' .
            '</div>';

        $contenido .=
            '<div style="margin-top: 40px;"></div>';

        $contenido .=
            '<div><b>' .
            $entrega->destinatario .
            '</b></div>';

        $contenido .=
            '<div>' .
            $razonsocial .
            '</div>';

        $contenido .=
            '<div>' .
            $direccion .
            '</div>';

        $contenido .=
            '<div style="margin-top: 40px;">' .
            'P r e s e n t e.' .
            '</div>';

        $contenido .=
            '<div style="margin-top: 10px;">' .
            'Se hace entrega de la siguiente documentación:' .
            '</div>';

        $contenido .=
            '<table style="width: 100%;border: 1px solid #ddd;' .
            'border-collapse: collapse;margin-top: 30px;">';

        $contenido .= '<thead>';

        $contenido .= '<tr>';

        $contenido .=
            '<th style="text-align: center;padding-top: 5px;' .
            'padding-bottom: 5px;background-color: #2D82DD;' .
            'color: white;">No.</th>';

        if ($cantidadEstaciones > 1) {

            $contenido .=
                '<th style="text-align: center;padding-top: 5px;' .
                'padding-bottom: 5px;background-color: #2D82DD;' .
                'color: white;">Razón Social</th>';
        }

        $contenido .=
            '<th style="text-align: center;padding-top: 5px;' .
            'padding-bottom: 5px;background-color: #2D82DD;' .
            'color: white;">Nombre del documento</th>';

        $contenido .=
            '<th style="text-align: center;padding-top: 5px;' .
            'padding-bottom: 5px;background-color: #2D82DD;' .
            'color: white;">Fecha del oficio</th>';

        $contenido .=
            '<th style="text-align: center;padding-top: 5px;' .
            'padding-bottom: 5px;background-color: #2D82DD;' .
            'color: white;">Original y/o copia</th>';

        $contenido .= '</tr>';

        $contenido .= '</thead>';

        $contenido .= '<tbody>';
        $num = 1;

        foreach ($documentos as $documento) {

            $razonSocialDocumento = 'S/I';

            if (
                $documento->id_estacion != 0 &&
                isset($estaciones[$documento->id_estacion])
            ) {

                $razonSocialDocumento =
                    $estaciones[$documento->id_estacion]->razonsocial;
            }

            $contenido .= '<tr>';

            $contenido .=
                '<td style="text-align: center;border: 1px solid #ddd;">' .
                '<b>' . $num . '</b>' .
                '</td>';

            if ($cantidadEstaciones > 1) {

                $contenido .=
                    '<td style="text-align: center;border: 1px solid #ddd;">' .
                    $razonSocialDocumento .
                    '</td>';
            }

            $contenido .=
                '<td style="text-align: center;border: 1px solid #ddd;">' .
                $documento->documento .
                '</td>';

            $contenido .=
                '<td style="text-align: center;border: 1px solid #ddd;">' .
                formatearFecha($documento->fecha->format('Y-m-d')) .
                '</td>';

            $contenido .=
                '<td style="text-align: center;border: 1px solid #ddd;">' .
                $documento->detalle .
                '</td>';

            $contenido .= '</tr>';

            $num++;
        }

        $contenido .= '</tbody>';

        $contenido .= '</table>';

        $contenido .=
            '<div style="margin-top: 30px;">' .
            '<b>Nota: Es importante mencionar que estos documentos ' .
            'deben estar bien archivados en la estación de servicio.</b>' .
            '</div>';

        $contenido .=
            '<div style="margin-top: 30px;">Recibido</div>';

        $contenido .=
            '<div style="margin-top: 0px;">' .
            '<b>Nombre:</b> ' .
            $nombre .
            '</div>';

        $contenido .=
            '<div style="margin-top: 0px;">' .
            '<b>Fecha:</b> ' .
            ($fechaE ? formatearFecha($fechaE) : '') .
            ($horaE
                ? ', ' . date('g:i a', strtotime($horaE))
                : '') .
            '</div>';

        return $contenido;
    }
}
