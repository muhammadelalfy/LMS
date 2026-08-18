<?php

namespace App\Services;

use App\Models\ExamTemplate;
use TCPDF;

class ExamPaperPdfService
{
    public function render(ExamTemplate $template): string
    {
        $template->loadMissing(['department', 'questions']);

        $html = view('pdf.exam-paper-tcpdf', [
            'template' => $template,
            'watermark' => $template->watermark_text ?: 'الامتياز في الرياضيات',
        ])->render();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->setRTL(true);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }
}
