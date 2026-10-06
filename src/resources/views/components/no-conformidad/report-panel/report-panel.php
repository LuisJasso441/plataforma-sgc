<?php

use App\Enums\NcStage;
use App\Models\NcAttachment;
use App\Models\NonConformity;
use App\Support\NcReportReader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public NonConformity $nc;

    public $report = null;          // archivo Excel
    public string $returnReason = '';

    /**
     * Líder sube (o vuelve a subir) el reporte → Reporte en revisión.
     * Nota: NO llamarlo upload(): choca con $wire.upload() de Livewire.
     */
    public function sendReport(): void
    {
        Gate::authorize('uploadReport', $this->nc);

        if (! in_array($this->nc->stage, [NcStage::PendienteReporte, NcStage::ReporteDevuelto], true)) {
            $this->backWith('error', 'En esta etapa no se puede subir el reporte.');
            return;
        }

        $this->validate([
            'report' => ['required', 'file', 'extensions:xlsx,xls,xlsm', 'max:10240'],
        ], [], ['report' => 'reporte']);

        // Lectura completa del reporte (Pasos 1 a 7)
        try {
            $data = NcReportReader::read($this->report->getRealPath());
        } catch (\RuntimeException $e) {
            $this->addError('report', $e->getMessage());
            return;
        } catch (\Throwable) {
            $this->addError('report', 'No se pudo leer el archivo. Verifica que sea el formato oficial guardado como Excel (.xlsx).');
            return;
        }

        // Fecha de disparo = campo "Fecha" del Paso 1
        $triggerDate = $data['fecha'];

        if (! $triggerDate) {
            $this->addError('report', 'No se pudo leer la fecha del reporte (Paso 1 › Datos generales › Fecha). Verifica que la fecha esté capturada.');
            return;
        }

        if ($triggerDate->isFuture()) {
            $this->addError('report', "La fecha del reporte ({$triggerDate->format('d/m/Y')}) es posterior a hoy. Corrígela en el formato.");
            return;
        }

        DB::transaction(function () use ($triggerDate, $data) {
            $from = $this->nc->stage;

            $attachment = NcAttachment::storeUpload(
                $this->report,
                $this->nc,
                NcAttachment::TYPE_REPORTE,
                "no-conformidad/{$this->nc->id}/reportes",
            );

            $this->nc->update([
                'stage'        => NcStage::ReporteEnRevision,
                'trigger_date' => $triggerDate,
                // Paso 2: si viene vacío se conserva la descripción actual
                'description'  => $data['descripcion'] !== '' ? $data['descripcion'] : $this->nc->description,
                // Pasos 3 a 7 (incluye las acciones que prellenan la captura)
                'report_data'  => Arr::except($data, ['fecha', 'descripcion']),
            ]);

            $this->nc->log(
                'reporte_enviado',
                sprintf(
                    '%s · Fecha de reunión: %s · %d acción(es) definitiva(s) leída(s)',
                    $attachment->original_name,
                    $triggerDate->format('d/m/Y'),
                    count($data['acciones']),
                ),
                $from,
                NcStage::ReporteEnRevision,
            );
        });

        $this->backWith('status', 'Reporte enviado a Calidad para revisión.');
    }

    /** Calidad aprueba el reporte → Captura de acciones */
    public function approve(): void
    {
        Gate::authorize('review', $this->nc);

        if ($this->nc->stage !== NcStage::ReporteEnRevision) {
            $this->backWith('error', 'El reporte no está en revisión.');
            return;
        }

        DB::transaction(function () {
            $from = $this->nc->stage;
            $this->nc->update(['stage' => NcStage::CapturaAcciones]);
            $this->nc->log('reporte_aprobado', null, $from, NcStage::CapturaAcciones);
        });

        $this->backWith('status', 'Reporte aprobado. El líder ya puede capturar las acciones definitivas.');
    }

    /** Calidad devuelve el reporte al líder con motivo */
    public function returnReport(): void
    {
        Gate::authorize('review', $this->nc);

        if ($this->nc->stage !== NcStage::ReporteEnRevision) {
            $this->backWith('error', 'El reporte no está en revisión.');
            return;
        }

        $this->validate([
            'returnReason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['returnReason' => 'motivo']);

        DB::transaction(function () {
            $from = $this->nc->stage;
            $this->nc->update(['stage' => NcStage::ReporteDevuelto]);
            $this->nc->log('reporte_devuelto', trim($this->returnReason), $from, NcStage::ReporteDevuelto);
        });

        $this->backWith('status', 'Reporte devuelto al líder de solución.');
    }

    protected function backWith(string $type, string $message): void
    {
        session()->flash($type, $message);

        $this->redirect(route('no-conformidad.show', $this->nc), navigate: true);
    }

    public function with(): array
    {
        $stage = $this->nc->stage;

        return [
            'reports'    => $this->nc->reports()->with('uploader')->get(),
            'canUpload'  => in_array($stage, [NcStage::PendienteReporte, NcStage::ReporteDevuelto], true)
                && Gate::allows('uploadReport', $this->nc),
            'canReview'  => $stage === NcStage::ReporteEnRevision && Gate::allows('review', $this->nc),
            'lastReturn' => $stage === NcStage::ReporteDevuelto
                ? $this->nc->logs()->where('event', 'reporte_devuelto')->value('comment')
                : null,
        ];
    }
};