<?php

namespace App\Http\Controllers;

use App\Models\NonConformity;
use App\Support\NcReportTemplate;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NcReportDownloadController extends Controller
{
    public function __invoke(NonConformity $nonConformity): BinaryFileResponse
    {
        Gate::authorize('downloadReport', $nonConformity);

        $config = config('sga.reporte_nc');
        $nc = $nonConformity->load(['process', 'subprocess', 'leader']);

        $path = NcReportTemplate::fill($config['plantilla'], [
            'codigo'         => $config['codigo'],
            'revision'       => $config['revision'],
            'fecha_revision' => $config['fecha_revision'],
            'folio'          => $nc->folio,
            'proceso'        => $nc->process->name . ($nc->subprocess ? ' / ' . $nc->subprocess->name : ''),
            'lider'          => $nc->leader?->name,
            'descripcion'    => $nc->description ?? $nc->initial_description,
            'origen'         => $nc->origin->value,
        ], $config['logo']);

        // Mismo nombre del formato original + folio: "Reporte de no conformidad y acción correctiva_AC-26-01.xlsx"
        $downloadName = pathinfo($config['plantilla'], PATHINFO_FILENAME) . "_{$nc->folio}.xlsx";

        return response()
            ->download($path, $downloadName)
            ->deleteFileAfterSend();
    }
}