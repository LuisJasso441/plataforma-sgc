<?php

use App\Enums\NcActionStatus;
use App\Enums\NcStage;
use App\Models\NcAction;
use App\Models\NcAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public NcAction $action;

    public array $files = [];          // evidencias a subir
    public string $rejectReason = '';

    /** Líder sube evidencias y las envía a revisión */
    public function submitEvidence(): void
    {
        $nc = $this->action->nonConformity;

        Gate::authorize('uploadEvidence', [$nc, $this->action]);

        if ($nc->stage !== NcStage::EnImplementacion
            || ! in_array($this->action->status, [NcActionStatus::Pendiente, NcActionStatus::Rechazada], true)) {
            $this->backWith('error', 'Esta acción no admite evidencias en este momento.');
            return;
        }

        $this->validate([
            'files'   => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:20480', 'extensions:jpg,jpeg,png,webp,pdf,xlsx,xls,xlsm,docx,doc,pptx'],
        ], [], [
            'files'   => 'evidencias',
            'files.*' => 'evidencia',
        ]);

        DB::transaction(function () use ($nc) {
            foreach ($this->files as $file) {
                NcAttachment::storeUpload(
                    $file,
                    $this->action,
                    NcAttachment::TYPE_EVIDENCIA,
                    "no-conformidad/{$nc->id}/acciones/{$this->action->id}",
                );
            }

            $this->action->update(['status' => NcActionStatus::EnRevision]);

            $nc->log('evidencia_enviada', sprintf('Acción %d · %d archivo(s)', $this->action->number, count($this->files)));
        });

        $this->backWith('status', "Evidencia de la acción {$this->action->number} enviada a Calidad.");
    }

    /** Calidad valida la acción: fecha real = hoy */
    public function validateAction(): void
    {
        $nc = $this->action->nonConformity;

        Gate::authorize('review', $nc);

        if ($nc->stage !== NcStage::EnImplementacion || $this->action->status !== NcActionStatus::EnRevision) {
            $this->backWith('error', 'La acción no está en revisión.');
            return;
        }

        $completed = false;

        DB::transaction(function () use ($nc, &$completed) {
            $this->action->update([
                'status'          => NcActionStatus::Validada,
                'actual_end_date' => today(),
                'review_comment'  => null,
                'reviewed_by'     => auth()->id(),
                'reviewed_at'     => now(),
            ]);

            $nc->log('accion_validada', "Acción {$this->action->number}");

            // Si todas están validadas, refreshDates fija la fecha real de cierre
            $nc->refreshDates();

            if ($nc->actual_close_date) {
                $from = $nc->stage;
                $nc->update(['stage' => NcStage::EnVerificacion]);

                $nc->log('implementacion_completa', sprintf(
                    'Cierre real: %s · Verificación de efectividad: %s',
                    $nc->actual_close_date->format('d/m/Y'),
                    $nc->verification_date->format('d/m/Y'),
                ), $from, NcStage::EnVerificacion);

                $completed = true;
            }

            $nc->refreshStatus();
        });

        $this->backWith('status', $completed
            ? "Acción {$this->action->number} validada. Todas las acciones están cumplidas: la NC pasó a verificación de efectividad."
            : "Acción {$this->action->number} validada.");
    }

    /** Calidad rechaza la evidencia con motivo */
    public function rejectAction(): void
    {
        $nc = $this->action->nonConformity;

        Gate::authorize('review', $nc);

        if ($nc->stage !== NcStage::EnImplementacion || $this->action->status !== NcActionStatus::EnRevision) {
            $this->backWith('error', 'La acción no está en revisión.');
            return;
        }

        $this->validate([
            'rejectReason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['rejectReason' => 'motivo']);

        DB::transaction(function () use ($nc) {
            $this->action->update([
                'status'         => NcActionStatus::Rechazada,
                'review_comment' => trim($this->rejectReason),
                'reviewed_by'    => auth()->id(),
                'reviewed_at'    => now(),
            ]);

            $nc->log('accion_rechazada', "Acción {$this->action->number}: " . trim($this->rejectReason));
            $nc->refreshStatus();
        });

        $this->backWith('status', "Evidencia de la acción {$this->action->number} rechazada. El líder deberá subir nuevas evidencias.");
    }

    // Edición por Calidad (acciones no validadas)
    public string $editActivity = '';
    public string $editResponsible = '';
    public string $editDate = '';

    public function mount(NcAction $action): void
    {
        $this->action          = $action;
        $this->editActivity    = $action->activity;
        $this->editResponsible = $action->responsible;
        $this->editDate        = $action->commitment_date->format('Y-m-d');
    }

    /** Calidad corrige actividad, responsable o fecha compromiso */
    public function saveEdit(): void
    {
        $nc = $this->action->nonConformity;

        Gate::authorize('editImplementationActions', $nc);

        if ($this->action->status === NcActionStatus::Validada) {
            $this->backWith('error', 'Una acción validada ya no se puede modificar.');
            return;
        }

        $this->validate([
            'editActivity'    => ['required', 'string', 'max:2000'],
            'editResponsible' => ['required', 'string', 'max:150'],
            'editDate'        => ['required', 'date', 'after_or_equal:' . $nc->trigger_date->format('Y-m-d')],
        ], [], [
            'editActivity'    => 'actividad',
            'editResponsible' => 'responsable',
            'editDate'        => 'fecha compromiso',
        ]);

        $changes = [];
        if (trim($this->editActivity) !== $this->action->activity) {
            $changes[] = 'actividad';
        }
        if (trim($this->editResponsible) !== $this->action->responsible) {
            $changes[] = 'responsable: ' . trim($this->editResponsible);
        }
        if ($this->editDate !== $this->action->commitment_date->format('Y-m-d')) {
            $changes[] = 'fecha compromiso ' . $this->action->commitment_date->format('d/m/Y')
                . ' → ' . \Illuminate\Support\Carbon::parse($this->editDate)->format('d/m/Y');
        }

        if (! $changes) {
            $this->backWith('status', 'No hubo cambios en la acción.');
            return;
        }

        DB::transaction(function () use ($nc, $changes) {
            $this->action->update([
                'activity'        => trim($this->editActivity),
                'responsible'     => trim($this->editResponsible),
                'commitment_date' => $this->editDate,
            ]);

            $nc->refreshDates();
            $nc->refreshStatus();
            $nc->log('acciones_modificadas', "Acción {$this->action->number}: " . implode(' · ', $changes));
        });

        $this->backWith('status', "Acción {$this->action->number} actualizada.");
    }

    /** Calidad quita una acción sin evidencias (las siguientes se renumeran) */
    public function removeAction(): void
    {
        $nc = $this->action->nonConformity;

        Gate::authorize('editImplementationActions', $nc);

        if ($this->action->status === NcActionStatus::Validada || $this->action->evidences()->exists()) {
            $this->backWith('error', 'Solo se pueden quitar acciones sin evidencias.');
            return;
        }

        if ($nc->actions()->count() <= 1) {
            $this->backWith('error', 'La NC debe conservar al menos una acción definitiva.');
            return;
        }

        $number    = $this->action->number;
        $activity  = \Illuminate\Support\Str::limit($this->action->activity, 80);
        $completed = false;

        DB::transaction(function () use ($nc, $number, $activity, &$completed) {
            $this->action->delete();

            foreach ($nc->actions()->where('number', '>', $number)->get() as $next) {
                $next->update(['number' => $next->number - 1]);
            }

            $nc->refreshDates();
            $nc->log('acciones_modificadas', "Acción {$number} eliminada: {$activity}");

            // Si las que quedan ya están todas validadas, la implementación terminó
            if ($nc->actual_close_date && $nc->stage === NcStage::EnImplementacion) {
                $nc->update(['stage' => NcStage::EnVerificacion]);
                $nc->log('implementacion_completa', sprintf(
                    'Cierre real: %s · Verificación de efectividad: %s',
                    $nc->actual_close_date->format('d/m/Y'),
                    $nc->verification_date->format('d/m/Y'),
                ), NcStage::EnImplementacion, NcStage::EnVerificacion);
                $completed = true;
            }

            $nc->refreshStatus();
        });

        $this->backWith('status', $completed
            ? "Acción {$number} eliminada. Las acciones restantes ya estaban validadas: la NC pasó a verificación."
            : "Acción {$number} eliminada.");
    }

    protected function backWith(string $type, string $message): void
    {
        session()->flash($type, $message);

        $this->redirect(route('no-conformidad.show', $this->action->non_conformity_id), navigate: true);
    }

    public function with(): array
    {
        $nc = $this->action->nonConformity;
        $inImplementation = $nc->stage === NcStage::EnImplementacion;

        return [
            'nc'        => $nc,
            'evidences' => $this->action->evidences()->with('uploader')->get(),
            'canUpload' => $inImplementation
                && in_array($this->action->status, [NcActionStatus::Pendiente, NcActionStatus::Rechazada], true)
                && Gate::allows('uploadEvidence', [$nc, $this->action]),
            'canReview' => $inImplementation
                && $this->action->status === NcActionStatus::EnRevision
                && Gate::allows('review', $nc),
        ];
    }
};