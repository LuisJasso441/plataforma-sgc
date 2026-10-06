<?php

use App\Enums\NcStage;
use App\Models\NonConformity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public NonConformity $nc;

    public string $description = '';

    /** true si las acciones vienen del reporte y aún no hay borrador guardado */
    public bool $fromReport = false;

    /** @var list<array{activity:string, responsible:string, commitment_date:string}> */
    public array $actions = [];

    public function mount(NonConformity $nc): void
    {
        $this->nc = $nc;
        $this->description = $nc->description ?? $nc->initial_description;

        $saved = $nc->actions()->get()->map(fn ($a) => [
            'activity'        => $a->activity,
            'responsible'     => $a->responsible,
            'commitment_date' => $a->commitment_date->format('Y-m-d'),
        ])->all();

        // Sin borrador guardado: se prellenan con las acciones del reporte (Paso 7)
        $reportActions = collect($nc->report_data['acciones'] ?? [])->map(fn ($a) => [
            'activity'        => $a['actividad'] ?? '',
            'responsible'     => $a['responsable'] ?? '',
            'commitment_date' => $a['fecha_compromiso'] ?? '',
        ])->all();

        $this->fromReport = ! $saved && $reportActions;
        $this->actions    = $saved ?: ($reportActions ?: [$this->emptyAction()]);
    }

    protected function emptyAction(): array
    {
        return ['activity' => '', 'responsible' => '', 'commitment_date' => ''];
    }

    public function addAction(): void
    {
        $this->actions[] = $this->emptyAction();
    }

    public function removeAction(int $index): void
    {
        unset($this->actions[$index]);
        $this->actions = array_values($this->actions) ?: [$this->emptyAction()];
    }

    protected function rules(): array
    {
        return [
            'description'               => ['required', 'string', 'min:10', 'max:5000'],
            'actions'                   => ['required', 'array', 'min:1', 'max:30'],
            'actions.*.activity'        => ['required', 'string', 'max:2000'],
            'actions.*.responsible'     => ['required', 'string', 'max:150'],
            'actions.*.commitment_date' => [
                'required', 'date',
                'after_or_equal:' . $this->nc->trigger_date->format('Y-m-d'),
            ],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'description'               => 'descripción actualizada',
            'actions.*.activity'        => 'actividad',
            'actions.*.responsible'     => 'responsable',
            'actions.*.commitment_date' => 'fecha compromiso',
        ];
    }

    public function saveDraft(): void
    {
        $this->persist(submit: false);
    }

    public function submit(): void
    {
        $this->persist(submit: true);
    }

    protected function persist(bool $submit): void
    {
        Gate::authorize('manageActions', $this->nc);

        if ($this->nc->stage !== NcStage::CapturaAcciones) {
            $this->backWith('error', 'En esta etapa ya no se pueden capturar acciones.');
            return;
        }

        if (! $this->nc->trigger_date) {
            $this->backWith('error', 'La NC no tiene fecha de disparo. Debe leerse del reporte vigente.');
            return;
        }

        $this->validate();

        DB::transaction(function () use ($submit) {
            $this->nc->update([
                'description' => trim($this->description),
            ]);

            // En captura todavía no hay evidencias: se reescriben las acciones completas
            $this->nc->actions()->delete();

            foreach (array_values($this->actions) as $i => $action) {
                $this->nc->actions()->create([
                    'number'          => $i + 1,
                    'activity'        => trim($action['activity']),
                    'responsible'     => trim($action['responsible']),
                    'commitment_date' => $action['commitment_date'],
                ]);
            }

            // Implementación = última fecha compromiso; Verificación = +1 mes
            $this->nc->refreshDates();

            if ($submit) {
                $from = $this->nc->stage;
                $this->nc->update(['stage' => NcStage::EnImplementacion]);

                $this->nc->log('acciones_capturadas', sprintf(
                    '%d acción(es) · Implementación: %s · Verificación: %s',
                    count($this->actions),
                    $this->nc->commitment_date->format('d/m/Y'),
                    $this->nc->verification_date->format('d/m/Y'),
                ), $from, NcStage::EnImplementacion);
            }

            $this->nc->refreshStatus();
        });

        $this->backWith('status', $submit
            ? "Acciones registradas. La NC {$this->nc->folio} pasó a implementación."
            : 'Borrador guardado.');
    }

    protected function backWith(string $type, string $message): void
    {
        session()->flash($type, $message);

        $this->redirect(route('no-conformidad.show', $this->nc), navigate: true);
    }

    public function with(): array
    {
        $lastCommitment = collect($this->actions)->pluck('commitment_date')->filter()->max();

        return [
            'editable'            => $this->nc->stage === NcStage::CapturaAcciones
                && Gate::allows('manageActions', $this->nc),
            'savedActions'        => $this->nc->actions()->get(),
            'previewCommitment'   => $lastCommitment ? Carbon::parse($lastCommitment) : null,
            'previewVerification' => $lastCommitment ? Carbon::parse($lastCommitment)->addMonthNoOverflow() : null,
        ];
    }
};