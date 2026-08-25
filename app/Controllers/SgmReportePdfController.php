<?php

namespace App\Controllers;

use App\Reports\Sgm\FormatoSgm1;
use App\Reports\Sgm\FormatoSgm2;
use App\Reports\Sgm\FormatoSgm3;
use App\Reports\Sgm\FormatoSgm4;
use App\Reports\Sgm\FormatoSgm5;
use App\Reports\Sgm\FormatoSgm6;
use App\Reports\Sgm\FormatoSgm7;
use App\Reports\Sgm\FormatoSgm8;
use App\Reports\Sgm\FormatoSgm9;
use App\Reports\Sgm\FormatoSgm10;
use App\Reports\Sgm\FormatoSgm11;
use App\Reports\Sgm\FormatoSgm12;
use App\Reports\Sgm\FormatoSgm13;
use App\Reports\Sgm\FormatoSgm14;
use App\Reports\Sgm\FormatoSgm15;
use App\Reports\Sgm\FormatoSgm16;
use App\Reports\Sgm\FormatoSgm17;
use App\Reports\Sgm\FormatoSgm18;
use App\Reports\Sgm\FormatoSgm19;
use App\Reports\Sgm\FormatoSgm20;
use App\Reports\Sgm\FormatoSgm21;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmReportePdfController
{
    public function reportePdf(int $idestacion, int $year): void
    {

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Reporte anual SGM ' . $year . '</title>';
        $html .= '<style>' . $css . '</style>';
        $html .= '</head>';
        $html .= '<body class="fs-6">';

        $html .= (new FormatoSgm1())->generar($idestacion, $year);
        $html .= (new FormatoSgm2())->generar($idestacion, $year);
        $html .= (new FormatoSgm3())->generar($idestacion, $year);
        $html .= (new FormatoSgm4())->generar($idestacion, $year);
        $html .= (new FormatoSgm5())->generar($idestacion, $year);
        $html .= (new FormatoSgm6())->generar($idestacion, $year);
        $html .= (new FormatoSgm7())->generar($idestacion, $year);
        $html .= (new FormatoSgm8())->generar($idestacion, $year);
        $html .= (new FormatoSgm9())->generar($idestacion, $year);
        $html .= (new FormatoSgm10())->generar($idestacion, $year);
        $html .= (new FormatoSgm11())->generar($idestacion, $year);
        $html .= (new FormatoSgm12())->generar($idestacion, $year);
        $html .= (new FormatoSgm13())->generar($idestacion, $year);
        $html .= (new FormatoSgm14())->generar($idestacion, $year);
        $html .= (new FormatoSgm15())->generar($idestacion, $year);
        $html .= (new FormatoSgm16())->generar($idestacion, $year);
        $html .= (new FormatoSgm17())->generar($idestacion, $year);
        $html .= (new FormatoSgm18())->generar($idestacion, $year);
        $html .= (new FormatoSgm19())->generar($idestacion, $year);
        $html .= (new FormatoSgm20())->generar($idestacion, $year);
        $html .= (new FormatoSgm21())->generar($idestacion, $year);

        $html .= '</body>';
        $html .= '</html>';

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream(
            'Reporte anual SGM ' . $year . '.pdf',
            [
                'Attachment' => true
            ]
        );
    }
}
