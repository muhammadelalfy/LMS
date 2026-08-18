<?php

namespace App\Services;

use App\Models\ExamTemplate;
use Dompdf\Dompdf;
use Dompdf\Options;

class ExamPaperPdfService
{
    public function render(ExamTemplate $template): string
    {
        $template->loadMissing(['department', 'questions']);

        $html = view('pdf.exam-paper', [
            'template' => $template,
            'watermark' => $template->watermark_text ?: 'الامتياز في الرياضيات',
            'watermarkOpacity' => max(0, min(50, (int) $template->watermark_opacity)) / 100,
        ])->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
