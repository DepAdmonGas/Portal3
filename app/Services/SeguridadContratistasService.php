<?php

namespace App\Services;

use App\Models\Estacion;
use App\Models\Sasisopa\RequisicionObra;
use App\Models\Sasisopa\RequisicionObraFormato12;
use App\Models\Sasisopa\RequisicionObraFormato12Procedimiento;
use App\Models\Sasisopa\RequisicionObraCartaResponsiva;
use Illuminate\Database\Capsule\Manager as Capsule;

class SeguridadContratistasService
{
    public static function createRequisicionObra(
        int $idEstacion,
        int $idUsuario,
        string $fecha,
        string $descripcion,
        string $justificacion,
        string $proveedor = ''
    ): RequisicionObra {

        return Capsule::transaction(function () use (
            $idEstacion,
            $idUsuario,
            $fecha,
            $descripcion,
            $justificacion,
            $proveedor
        ) {

            $estacion = Estacion::findOrFail($idEstacion);
            $folio = self::folioRequisicionObraEstacion($idEstacion);

            $requisicion = RequisicionObra::create([
                'id_estacion' => $idEstacion,
                'id_usuario' => $idUsuario,
                'no_folio' => $folio,
                'fecha' => $fecha . ' ' . date('H:i:s'),
                'descripcion' => $descripcion,
                'justificacion' => $justificacion,
                'proveedor' => $proveedor,
                'estado' => 1
            ]);

            self::validarCartaResponsiva(
                $requisicion->id,
                (string) ($estacion->di_municipio ?? ''),
                (string) ($estacion->di_estado ?? ''),
                (string) ($estacion->apoderado_legal ?? ''),
                (string) ($estacion->razonsocial ?? ''),
                (string) ($estacion->direccioncompleta ?? ''),
                (string) ($estacion->firma ?? '')
            );

            self::validarRequisicionObra(
                $requisicion->id,
                (string) ($estacion->di_municipio ?? ''),
                (string) ($estacion->di_estado ?? '')
            );

            return $requisicion;
        });
    }

    private static function folioRequisicionObraEstacion(
    int $idEstacion
    ): int {

        $ultimo = RequisicionObra::query()
            ->where('id_estacion',$idEstacion)
            ->max('no_folio');

        return $ultimo
            ? $ultimo + 1
            : 1;
    }

    public static function validarRequisicionObra(
        int $idRequisicion,
        string $municipio,
        string $estado
    ): bool {

        $existe = RequisicionObraFormato12::query()
            ->where('id_requisicion',$idRequisicion)
            ->exists();
        if ($existe) {
            return false;
        }

        $requisionObra12 = RequisicionObraFormato12::create([

            'id_requisicion' => $idRequisicion,
            'archivo' => '',
            'dia' => date('d'),
            'mes' => nombremes(date('m')),
            'year' => date('Y'),
            'municipio' => $municipio,
            'estado' => $estado,
            'trabajo_realizar' => '',
            'descripcion' => '',
            'area' => '',
            'fecha_inicio' => '',
            'fecha_termino' => '',
            'hora_inicio' => '',
            'hora_termino' => '',
            'prestador_servicio' => '',
            'cprtp' => 0,
            'cteppc' => 0,
            'nombre_empresa' => '',
            'nombre_responsable' => ''
        ]);

        RequisicionObraFormato12Procedimiento::insert([

            [
                'id_requisicion' => $requisionObra12->id,
                'detalle' => 'Trabajos en alturas',
                'valor' => 0
            ],

            [
                'id_requisicion' => $requisionObra12->id,
                'detalle' => 'Trabajos en áreas confinadas',
                'valor' => 0
            ],

            [
                'id_requisicion' => $requisionObra12->id,
                'detalle' => 'Trabajos que generen fuentes de ignición',
                'valor' => 0
            ],

            [
                'id_requisicion' => $requisionObra12->id,
                'detalle' => 'Etiquetado, bloqueo y candadeo de líneas eléctricas',
                'valor' => 0
            ],

            [
                'id_requisicion' => $requisionObra12->id,
                'detalle' => 'Etiquetado, bloqueo y candadeo de líneas de productos',
                'valor' => 0
            ]
        ]);

        return true;
    }

    public static function validarCartaResponsiva(
        int $idRequisicion,
        string $municipio,
        string $estado,
        string $apoderadoLegal,
        string $razonSocial,
        string $direccion,
        string $firmaApoderadoLegal
    ): RequisicionObraCartaResponsiva {

        return RequisicionObraCartaResponsiva::firstOrCreate(

            [
                'id_requisicion' => $idRequisicion
            ],

            [
                'archivo' => '',
                'dia' => date('d'),
                'mes' => nombremes(date('m')),
                'year' => date('Y'),
                'municipio' => $municipio,
                'estado' => $estado,
                'representante_legal' => $apoderadoLegal,
                'razon_social' => $razonSocial,
                'domicilio' => $direccion,
                'apoderado_legal' => $apoderadoLegal,
                'firma' => $firmaApoderadoLegal
            ]
        );
    }
    
}