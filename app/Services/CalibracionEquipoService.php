<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;

use App\Models\Sgm\CalibracionEquipo;
use App\Models\Sgm\CalibracionEquipoDetalle;
use App\Models\Sgm\CalibracionEquipoDispensario;
use App\Models\Sgm\CalibracionEquipoJarra;
use App\Models\Sgm\CalibracionEquipoSonda;
use App\Models\Sgm\CalibracionEquipoTanque;

use App\Models\Sasisopa\Dispensario;
use App\Models\Sasisopa\JarraPatron;
use App\Models\Sasisopa\SondasMedicion;
use App\Models\Sasisopa\TanqueAlmacenamiento;
use Carbon\Carbon;

class CalibracionEquipoService
{
    public function agregarCalibracionEquipo(
        int $id_estacion,
        int $id_usuario,
        string $equipo,
        $fecha = null
    ): array {

     $fecha_programada = $this->calcularProximaCalibracion($equipo, $fecha);

        return Capsule::transaction(function () use (
            $id_estacion,
            $id_usuario,
            $equipo,
            $fecha_programada
        ) {

            $calibracion = CalibracionEquipo::query()
                ->where('id_estacion', $id_estacion)
                ->where('equipo', $equipo)
                ->where('estado', 0)
                ->latest('id')
                ->first();

            if (!$calibracion) {

                $folio = (
                    CalibracionEquipo::query()
                        ->where('id_estacion', $id_estacion)
                        ->where('equipo', $equipo)
                        ->max('folio')
                ) + 1;

                $calibracion = CalibracionEquipo::create([
                    'id_estacion' => $id_estacion,
                    'id_usuario' => $id_usuario,
                    'folio' => $folio,
                    'fecha' => $fecha_programada,
                    'equipo' => $equipo,
                    'observaciones' => '',
                    'responsable_verificacion' => '',
                    'resultados' => '',
                    'categoria' => 1,
                    'estado' => 0
                ]);

                match ($equipo) {

                    'Dispensario' => $this->crearBitacoraDispensario(
                        $calibracion->id,
                        $id_estacion
                    ),

                    'Jarra patron' => $this->crearBitacoraJarraPatron(
                        $calibracion->id,
                        $id_estacion
                    ),

                    'Sondas de medición' => $this->crearBitacoraSondas(
                        $calibracion->id,
                        $id_estacion
                    ),

                    'Tanques de almacenamiento' => $this->crearBitacoraTanques(
                        $calibracion->id,
                        $id_estacion
                    ),

                    default => null
                };
            }

            $rutas = [
                'Dispensario' =>
                    'bitacora-calibracion-equipos-dispensario',

                'Jarra patron' =>
                    'bitacora-calibracion-equipos-jarra-patron',

                'Sondas de medición' =>
                    'bitacora-calibracion-equipos-sonda',

                'Tanques de almacenamiento' =>
                    'bitacora-calibracion-equipos-tanques-almacenamiento',
            ];

            return [
                'id' => $calibracion->id,
                'folio' => $calibracion->folio,
                'redirect' =>
                    $rutas[$equipo] . '/' . $calibracion->id
            ];
        });
    }

    private function detalle(
        int $idCalibracion,
        string $categoria
    ): void {

        CalibracionEquipoDetalle::create([
            'id_calibracion' => $idCalibracion,
            'categoria' => $categoria,
            'resultado' => ''
        ]);
    }

    private function crearBitacoraDispensario(
        int $idCalibracion,
        int $idEstacion
    ): void {

        $this->detalle(
            $idCalibracion,
            'Unidad de verificación'
        );

        $this->detalle(
            $idCalibracion,
            'No. de acreditación'
        );

        Dispensario::query()
            ->where('id_estacion', $idEstacion)
            ->where('estado', 1)
            ->get()
            ->each(function ($dispensario) use ($idCalibracion) {

                CalibracionEquipoDispensario::create([
                    'id_calibracion' => $idCalibracion,
                    'id_dispensario' => $dispensario->id,
                    'resultado1' => '',
                    'resultado2' => '',
                    'resultado3' => '',
                    'resultado4' => ''
                ]);
            });
    }

    private function crearBitacoraJarraPatron(
        int $idCalibracion,
        int $idEstacion
    ): void {

        collect([
            'Temperatura ambiente',
            'Presión atmosférica',
            'Humedad',
            'Liquido usado en la calibración',
            'Temperatura del líquido',
            'Laboratorio de calibración',
            'No. de acreditación',
            'Método de calibración'
        ])->each(fn($item) =>
            $this->detalle(
                $idCalibracion,
                $item
            )
        );

        JarraPatron::query()
            ->where('id_estacion', $idEstacion)
            ->get()
            ->each(function ($jarra) use ($idCalibracion) {

                CalibracionEquipoJarra::create([
                    'id_calibracion' => $idCalibracion,
                    'id_jarra' => $jarra->id,
                    'resultado1' => ''
                ]);
            });
    }

    private function crearBitacoraSondas(
        int $idCalibracion,
        int $idEstacion
    ): void {

        collect([
            'Unidad de verificación',
            'No. de acreditación',
            'Método usado para la calibración'
        ])->each(fn($item) =>
            $this->detalle(
                $idCalibracion,
                $item
            )
        );

        SondasMedicion::query()
            ->where('id_estacion', $idEstacion)
            ->get()
            ->each(function ($sonda) use ($idCalibracion) {

                CalibracionEquipoSonda::create([
                    'id_calibracion' => $idCalibracion,
                    'id_sonda' => $sonda->id,
                    'resultado1' => ''
                ]);
            });
    }

    private function crearBitacoraTanques(
        int $idCalibracion,
        int $idEstacion
    ): void {

        collect([
            'Unidad de verificación',
            'No. de acreditación',
            'Método usado para la calibración'
        ])->each(fn($item) =>
            $this->detalle(
                $idCalibracion,
                $item
            )
        );

        TanqueAlmacenamiento::query()
            ->where('id_estacion', $idEstacion)
            ->get()
            ->each(function ($tanque) use ($idCalibracion) {

                CalibracionEquipoTanque::create([
                    'id_calibracion' => $idCalibracion,
                    'id_tanque' => $tanque->id,
                    'resultado1' => '',
                    'resultado2' => '',
                    'resultados' => ''
                ]);
            });
    }

    //-------------------------------------------
    //---------- Finalizar ----------------------

    public function finalizar(
    int $idCalibracion
    ): void {

        Capsule::transaction(function () use (
            $idCalibracion
        ) {

            $calibracion =
                CalibracionEquipo::findOrFail(
                    $idCalibracion
                );

            $calibracion->update([
                'estado' => 1
            ]);

           $this->agregarCalibracionEquipo(
                $calibracion->id_estacion,
                $calibracion->id_usuario,
                $calibracion->equipo,
                $calibracion->fecha ? $calibracion->fecha->format('Y-m-d') : null
            );
        });
    }



private function calcularProximaCalibracion(string $equipo, ?string $fecha): ?string
{
    if (!$fecha) {
        return '';
    }

    $date = Carbon::parse($fecha);

    return match ($equipo) {

        'Dispensario' =>
            $date->copy()->addMonths(6)->format('Y-m-d'),

        'Jarra patron' =>
            $date->copy()->addYear()->format('Y-m-d'),

        'Sondas de medición' =>
            $date->copy()->addYears(2)->format('Y-m-d'),

        'Tanques de almacenamiento' =>
            $date->copy()->addYears(10)->format('Y-m-d'),

        default => $date->format('Y-m-d'),
    };
}
}