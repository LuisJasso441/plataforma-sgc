<?php

use App\Enums\NcStage;
use App\Models\NcProcess;
use App\Models\NonConformity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public NonConformity $nc;

    public ?string $effective = null;      // 'si' | 'no'
    public string $observations = '';
    public string $lessons_learned = '';
    public array $affectedProcesses = [];

    public function close(): void
    {
        Gate::authorize('review', $this->nc);

        if ($this->nc->stage !== NcStage::EnVerificacion) {
            $this->backWith('error', 'Esta NC no está en verificación de efectividad.');
            return;
        }

        if ($this->nc->verification_date->isFuture()) {
            $this->backWith('error', "La verificación está programada para el {$this->nc->verification_date->format('d/m/Y')}.");
            return;
        }

        $this->validate([
            'effective'           => ['required', Rule::in(['si', 'no'])],
            'observations'        => [Rule::requiredIf(fn () => $this->effective === 'no'), 'nullable', 'string', 'max:5000'],
            'lessons_learned'     => ['required', 'string', 'min:10', 'max:5000'],
            'affectedProcesses'   => ['array'],
            'affectedProcesses.*' => [Rule::exists('nc_processes', 'id')],
        ], [
            'effective.required'    => 'Indica si las acciones fueron efectivas.',
            'observations.required' => 'Explica por qué las acciones no fueron efectivas.',
        ], [
            'lessons_learned' => 'lección aprendida',
        ]);

        $isEffective = $this->effective === 'si';
        $newNc = null;

        DB::transaction(function () use ($isEffective, &$newNc) {
            $from = $this->nc->stage;
            $to   = $isEffective ? NcStage::Cerrada : NcStage::NoEfectiva;

            $this->nc->update([
                'is_effective'    => $isEffective,
                'observations'    => trim($this->observations) ?: null,
                'lessons_learned' => trim($this->lessons_learned),
                'stage'           => $to,
                'closed_by'       => auth()->id(),
                'closed_at'       => now(),
            ]);

            $this->nc->affectedProcesses()->sync($this->affectedProcesses);

            $this->nc->log(
                $isEffective ? 'verificada_efectiva' : 'verificada_no_efectiva',
                trim($this->observations) ?: null,
                $from,
                $to,
            );

            $this->nc->refreshStatus(); // Cerrada / No efectiva

            // No efectiva → nueva NC automática
            if (! $isEffective) {
                $newNc = NonConformity::createWithFolio([
                    'parent_id'           => $this->nc->id,
                    'issued_by'           => auth()->id(),
                    'department_id'       => $this->nc->department_id,
                    'nc_process_id'       => $this->nc->nc_process_id,
                    'nc_subprocess_id'    => $this->nc->nc_subprocess_id,
                    'origin'              => $this->nc->origin,
                    'initial_description' => $this->nc->description ?? $this->nc->initial_description,
                    'leader_id'           => $this->nc->leader_id,
                    'stage'               => NcStage::PendienteReporte,
                    'accepted_by'         => auth()->id(),
                    'accepted_at'         => now(),
                ]);

                $newNc->log(
                    'creada_por_no_efectiva',
                    "Generada automáticamente porque {$this->nc->folio} resultó no efectiva.",
                    null,
                    NcStage::PendienteReporte,
                );

                $this->nc->log('nueva_accion', "Folio de la nueva acción: {$newNc->folio}");
            }
        });

        $this->backWith('status', $isEffective
            ? "NC {$this->nc->folio} cerrada: acciones efectivas."
            : "NC {$this->nc->folio} marcada como no efectiva. Se abrió la nueva acción {$newNc->folio}.");
    }

    protected function backWith(string $type, string $message): void
    {
        session()->flash($type, $message);

        $this->redirect(route('no-conformidad.show', $this->nc), navigate: true);
    }

    public function with(): array
    {
        $inVerification = $this->nc->stage === NcStage::EnVerificacion;

        return [
            'isDue'      => $inVerification && $this->nc->verification_date && ! $this->nc->verification_date->isFuture(),
            'canVerify'  => $inVerification
                && $this->nc->verification_date && ! $this->nc->verification_date->isFuture()
                && Gate::allows('review', $this->nc),
            'processes'  => NcProcess::active()->get(),
            'affected'   => $this->nc->affectedProcesses()->orderBy('name')->get(),
            'reopenings' => $this->nc->reopenings()->get(),
        ];
    }
};