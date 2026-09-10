<?php

namespace App\Controllers;

use Illuminate\Database\Capsule\Manager as DB;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\CursoModulo;
use App\Models\Sasisopa\CursoTema;
use App\Models\Sasisopa\CursoTemaPregunta;
use App\Models\Sasisopa\CursoTemaPreguntaRespuesta;


use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;

class SistemasCursosController extends BaseController
{
    public function index(): void
    {
        $usuario = Usuario::find($this->userId());

        if (!$usuario || (int) $usuario->id_puesto !== 25) {
            header('Location: /home');
            exit;
        }

        $modulos = CursoModulo::query()
            ->select([
                'id',
                'num_modulo',
                'titulo',
            ])
            ->orderBy('num_modulo')
            ->get();

        $title = 'Cursos Configuración';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,

            'modulos' => $modulos,

            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],

            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/cursos/configuracion.datatable.init.js?v=' . time(),
                '/js/cursos/configuracion.actions.init.js?v=' . time()
            ]
        ];

        View::render(
            'cursos/configuracion',
            $data,
            'main'
        );
    }

    public function datatable(): void
    {
        try {

            $temas = CursoTema::query()
                ->select([
                    'tb_cursos_temas.id',
                    'tb_cursos_temas.id_modulo',
                    'tb_cursos_temas.num_tema',
                    'tb_cursos_temas.titulo',
                    'tb_cursos_temas.archivo',
                    'tb_cursos_temas.categoria',

                    'tb_cursos_modulos.id AS modulo_id',
                    'tb_cursos_modulos.num_modulo',
                    'tb_cursos_modulos.titulo AS modulo',
                ])
                ->join(
                    'tb_cursos_modulos',
                    'tb_cursos_temas.id_modulo',
                    '=',
                    'tb_cursos_modulos.id'
                )
                ->orderBy('tb_cursos_temas.num_tema')
                ->get();

            JsonResponse::custom([
                'success' => true,
                'data' => $temas,
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible cargar los cursos.',
                'data' => [],
            ]);
        }
    }

    public function crearModulo(): void
    {
        try {

            $data = $this->requestData();

            $titulo = trim(
                (string) ($data['titulo'] ?? '')
            );

            if ($titulo === '') {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Ingresa el título del módulo.',
                ]);

                return;
            }

            $ultimo = CursoModulo::query()
                ->max('num_modulo');

            $numModulo = ((int) $ultimo) + 1;

            $modulo = CursoModulo::create([
                'titulo' => $titulo,
                'num_modulo' => $numModulo,
            ]);

            JsonResponse::custom([
                'success' => true,
                'message' => 'Módulo agregado correctamente.',
                'data' => [
                    'id' => (int) $modulo->id,
                    'num_modulo' => (int) $modulo->num_modulo,
                    'titulo' => $modulo->titulo,
                ],
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible agregar el módulo.',
            ]);
        }
    }

    public function editarModulo(): void
    {
        try {

            $data = $this->requestData();

            $id = (int) ($data['id'] ?? 0);

            $titulo = trim(
                (string) ($data['titulo'] ?? '')
            );

            if ($id <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Módulo no válido.',
                ]);

                return;
            }

            if ($titulo === '') {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'El título del módulo no puede estar vacío.',
                ]);

                return;
            }

            $modulo = CursoModulo::find($id);

            if (!$modulo) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'El módulo no existe.',
                ]);

                return;
            }

            $modulo->titulo = $titulo;
            $modulo->save();

            JsonResponse::custom([
                'success' => true,
                'message' => 'Módulo actualizado correctamente.',
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible actualizar el módulo.',
            ]);
        }
    }

    public function crearTema(): void
    {
        try {

            $data = $this->requestData();

            $idModulo = (int) ($data['id_modulo'] ?? 0);

            $titulo = trim(
                (string) ($data['titulo'] ?? '')
            );

            if ($idModulo <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Selecciona un módulo.',
                ]);

                return;
            }

            if ($titulo === '') {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Ingresa el nombre del tema.',
                ]);

                return;
            }

            $modulo = CursoModulo::find($idModulo);

            if (!$modulo) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'El módulo seleccionado no existe.',
                ]);

                return;
            }

            $ultimoTema = CursoTema::query()
                ->where(
                    'id_modulo',
                    $idModulo
                )
                ->max('num_tema');

            $numTema = ((int) $ultimoTema) + 1;

            $tema = CursoTema::create([
                'id_modulo' => $idModulo,
                'num_tema' => $numTema,
                'titulo' => $titulo,
                'archivo' => '',
                'confi_mes' => 0,
                'confi_lista' => 0,
                'categoria' => '',
                'estado' => 1,
            ]);

            JsonResponse::custom([
                'success' => true,
                'message' => 'Tema agregado correctamente.',
                'data' => [
                    'id' => (int) $tema->id,
                ],
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'Error al crear',
            ]);
        }
    }

    public function editarTema(): void
    {
        try {

            $data = $this->requestData();

            $id = (int) ($data['id'] ?? 0);

            $campo = (string) ($data['campo'] ?? '');

            $valor = trim(
                (string) ($data['valor'] ?? '')
            );

            if ($id <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Tema no válido.',
                ]);

                return;
            }

            if (
                !in_array(
                    $campo,
                    [
                        'titulo',
                        'categoria',
                    ],
                    true
                )
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Campo no permitido.',
                ]);

                return;
            }

            $tema = CursoTema::find($id);

            if (!$tema) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'El tema no existe.',
                ]);

                return;
            }

            $tema->{$campo} = $valor;

            $tema->save();

            JsonResponse::custom([
                'success' => true,
                'message' => 'Tema actualizado correctamente.',
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible actualizar el tema.',
            ]);
        }
    }

    public function cuestionario(int $idTema): void
    {
        $tema = CursoTema::query()
            ->select([
                'id',
                'titulo',
            ])
            ->find($idTema);

        $preguntas = CursoTemaPregunta::query()
            ->select([
                'id',
                'id_tema',
                'num_pregunta',
                'titulo',
            ])
            ->where(
                'id_tema',
                $idTema
            )
            ->orderBy(
                'num_pregunta'
            )
            ->get();

        $idsPreguntas = $preguntas
            ->pluck('id');

        $respuestas = $idsPreguntas->isEmpty()
            ? collect()
            : CursoTemaPreguntaRespuesta::query()
            ->select([
                'id',
                'id_pregunta',
                'num_respuesta',
                'titulo',
                'valor',
            ])
            ->whereIn(
                'id_pregunta',
                $idsPreguntas
            )
            ->orderBy(
                'num_respuesta'
            )
            ->get()
            ->groupBy(
                'id_pregunta'
            );

        $usuario = Usuario::find($this->userId());

        if (!$usuario || (int) $usuario->id_puesto !== 25) {
            header('Location: /home');
            exit;
        }

        $title = 'Cuestionario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Cursos', '/cursos');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'tema' => $tema,
            'preguntas' => $preguntas,
            'respuestas' => $respuestas,

            'links' => [],

            'scripts' => [
                '/js/vendor.min.js',
                '/js/cursos/cuestionario.actions.init.js?v=' . time()
            ]
        ];

        View::render(
            'cursos/cuestionario',
            $data,
            'main'
        );
    }


    public function crearPregunta(): void
    {
        try {

            $data = $this->requestData();

            $idTema = (int) ($data['id_tema'] ?? 0);

            $titulo = trim(
                (string) ($data['titulo'] ?? '')
            );

            if (
                $idTema <= 0
                || $titulo === ''
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Ingresa la pregunta.',
                ]);

                return;
            }

            if (!CursoTema::find($idTema)) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'El tema no existe.',
                ]);

                return;
            }

            $ultimaPregunta = CursoTemaPregunta::query()
                ->where(
                    'id_tema',
                    $idTema
                )
                ->max(
                    'num_pregunta'
                );

            $numPregunta =
                ((int) $ultimaPregunta) + 1;

            $pregunta = CursoTemaPregunta::create([
                'id_tema' => $idTema,
                'num_pregunta' => $numPregunta,
                'titulo' => $titulo,
            ]);

            JsonResponse::custom([
                'success' => true,
                'message' => 'Pregunta agregada correctamente.',
                'data' => [
                    'id' => (int) $pregunta->id,
                ],
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }


    /**
     * =========================================================
     * EDITAR PREGUNTA
     * =========================================================
     */
    public function editarPregunta(): void
    {
        try {

            $data = $this->requestData();

            $id = (int) ($data['id'] ?? 0);

            $titulo = trim(
                (string) ($data['titulo'] ?? '')
            );

            if ($id <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Pregunta no válida.',
                ]);

                return;
            }

            $pregunta = CursoTemaPregunta::find($id);

            if (!$pregunta) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'La pregunta no existe.',
                ]);

                return;
            }

            $pregunta->titulo = $titulo;
            $pregunta->save();

            JsonResponse::custom([
                'success' => true,
                'message' => 'Pregunta actualizada correctamente.',
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible actualizar la pregunta.',
            ]);
        }
    }


    /**
     * =========================================================
     * CREAR RESPUESTA
     * =========================================================
     */
    public function crearRespuesta(): void
    {
        try {

            $data = $this->requestData();

            $idPregunta =
                (int) ($data['id_pregunta'] ?? 0);

            $titulo = trim(
                (string) ($data['titulo'] ?? '')
            );

            if ($idPregunta <= 0) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Selecciona una pregunta.',
                ]);

                return;
            }

            if ($titulo === '') {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Ingresa la respuesta.',
                ]);

                return;
            }

            if (!CursoTemaPregunta::find($idPregunta)) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'La pregunta no existe.',
                ]);

                return;
            }

            $ultimaRespuesta =
                CursoTemaPreguntaRespuesta::query()
                ->where(
                    'id_pregunta',
                    $idPregunta
                )
                ->max(
                    'num_respuesta'
                );

            $numRespuesta =
                ((int) $ultimaRespuesta) + 1;

            CursoTemaPreguntaRespuesta::create([
                'id_pregunta' => $idPregunta,
                'num_respuesta' => $numRespuesta,
                'titulo' => $titulo,
                'valor' => 0,
            ]);

            JsonResponse::custom([
                'success' => true,
                'message' => 'Respuesta agregada correctamente.',
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible agregar la respuesta.',
            ]);
        }
    }


    /**
     * =========================================================
     * RESPUESTA CORRECTA
     * =========================================================
     */
    public function respuestaCorrecta(): void
    {
        try {

            $data = $this->requestData();

            $idRespuesta =
                (int) ($data['id_respuesta'] ?? 0);

            $idPregunta =
                (int) ($data['id_pregunta'] ?? 0);

            if (
                $idRespuesta <= 0
                || $idPregunta <= 0
            ) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'Respuesta no válida.',
                ]);

                return;
            }

            $respuesta = CursoTemaPreguntaRespuesta::query()
                ->where(
                    'id',
                    $idRespuesta
                )
                ->where(
                    'id_pregunta',
                    $idPregunta
                )
                ->first();

            if (!$respuesta) {

                JsonResponse::custom([
                    'success' => false,
                    'message' => 'La respuesta no pertenece a esta pregunta.',
                ]);

                return;
            }

            $respuesta
                ->getConnection()
                ->transaction(
                    function () use (
                        $idPregunta,
                        $respuesta
                    ) {

                        CursoTemaPreguntaRespuesta::query()
                            ->where(
                                'id_pregunta',
                                $idPregunta
                            )
                            ->update([
                                'valor' => 0,
                            ]);

                        $respuesta->valor = 1;

                        $respuesta->save();
                    }
                );

            JsonResponse::custom([
                'success' => true,
                'message' => 'Respuesta asignada correctamente.',
            ]);
        } catch (\Throwable $e) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'No fue posible asignar la respuesta.',
            ]);
        }
    }


    /**
     * =========================================================
     * REQUEST
     * =========================================================
     */
    private function requestData(): array
    {
        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (is_array($input)) {
            return $input;
        }

        return $_POST ?? [];
    }
}
