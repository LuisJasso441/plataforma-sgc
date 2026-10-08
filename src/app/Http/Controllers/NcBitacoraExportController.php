<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\NonConformity;
use App\Support\NcBitacoraTemplate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NcBitacoraExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['search', 'status', 'process', 'year', 'department']);
        $config  = config('sga.bitacora_nc');

        $ncs = NonConformity::query()
            ->visibleTo($request->user())
            ->filter($filters)
            ->with(['department', 'process', 'subprocess', 'leader', 'issuer', 'affectedProcesses'])
            ->orderBy('folio_year')
            ->orderBy('folio_number')
            ->get();

        // Una hoja por departamento, en orden alfabético
        $groups = $ncs
            ->groupBy(fn (NonConformity $nc) => $nc->department->name)
            ->sortKeys()
            ->map(fn ($items) => $items->map(fn (NonConformity $nc) => [
                'B' => $nc->folio,
                'C' => $nc->trigger_date,
                'D' => $nc->process->name,
                'E' => $nc->subprocess?->name ?? 'N. A.',
                'F' => $nc->leader?->name,
                'G' => $nc->description ?? $nc->initial_description,
                'H' => $nc->issuer->name,
                'I' => $nc->commitment_date,
                'J' => $nc->verification_date,
                'K' => $nc->actual_close_date,
                'L' => $nc->status->label(),
                'M' => $nc->observations,
                'N' => $nc->lessons_learned,
                'O' => $nc->affectedProcesses->pluck('name')->implode(', '),
            ])->values()->all())
            ->all();

        $path = NcBitacoraTemplate::fill($config['plantilla'], $groups, [
            'codigo'         => $config['codigo'],
            'revision'       => $config['revision'],
            'fecha_revision' => $config['fecha_revision'],
        ], config('sga.reporte_nc.logo'));

        // "<nombre del formato>[_DEPARTAMENTO]_AAAA-MM-DD.xlsx"
        $department = ! empty($filters['department']) ? Department::find($filters['department'])?->name : null;
        $name = pathinfo($config['plantilla'], PATHINFO_FILENAME)
            . ($department ? "_{$department}" : '')
            . '_' . now()->format('Y-m-d') . '.xlsx';

        return response()->download($path, $name)->deleteFileAfterSend();
    }
}