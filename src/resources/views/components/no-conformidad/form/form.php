<?php

use App\Enums\NcOrigin;
use App\Enums\NcStage;
use App\Models\Department;
use App\Models\NcProcess;
use App\Models\NcSubprocess;
use App\Models\NonConformity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('No Conformidad')] class extends Component
{
    public ?NonConformity $nc = null;

    public ?int $nc_process_id = null;
    public $nc_subprocess_id = null; // opcional (vacío = N. A.)
    public ?int $department_id = null;
    public string $origin = '';
    public string $initial_description = '';

    public function mount(?NonConformity $nonConformity = null): void
    {
        if ($nonConformity && $nonConformity->exists) {
            Gate::authorize('correct', $nonConformity);

            $this->nc = $nonConformity;
            $this->nc_process_id = $nonConformity->nc_process_id;
            $this->nc_subprocess_id = $nonConformity->nc_subprocess_id;
            $this->department_id = $nonConformity->department_id;
            $this->origin = $nonConformity->origin->value;
            $this->initial_description = $nonConformity->initial_description;
        } else {
            Gate::authorize('create', NonConformity::class);
        }
    }

    protected function rules(): array
    {
        return [
            'nc_process_id'       => ['required', Rule::exists('nc_processes', 'id')->where('active', true)],
            'nc_subprocess_id'    => ['nullable', Rule::exists('nc_subprocesses', 'id')->where('active', true)],
            'department_id'       => ['required', Rule::exists('departments', 'id')->where('active', true)],
            'origin'              => ['required', Rule::enum(NcOrigin::class)],
            'initial_description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'nc_process_id'       => 'proceso',
            'nc_subprocess_id'    => 'sub-proceso',
            'department_id'       => 'departamento',
            'origin'              => 'origen de acción',
            'initial_description' => 'descripción',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['nc_subprocess_id'] = $data['nc_subprocess_id'] ?: null;

        if ($this->nc) {
            Gate::authorize('correct', $this->nc);

            $message = DB::transaction(function () use ($data) {
                $from = $this->nc->stage;

                // Si cambia el departamento, toma el siguiente folio del nuevo departamento
                $this->nc->moveToDepartment((int) $data['department_id']);
                $this->nc->update(Arr::except($data, ['department_id']));

                // Corrección del emisor: regresa a Calidad
                if ($from === NcStage::DevueltaEmisor) {
                    $this->nc->update(['stage' => NcStage::Solicitada]);
                    $this->nc->log('corregida', 'Solicitud corregida y reenviada a Calidad.', $from, NcStage::Solicitada);

                    return "NC {$this->nc->folio} corregida y reenviada a Calidad.";
                }

                // Edición libre de Calidad: no cambia la etapa
                $this->nc->log('editada', 'Datos de la solicitud editados.');

                return "NC {$this->nc->folio} actualizada.";
            });
        } else {
            Gate::authorize('create', NonConformity::class);

            $nc = DB::transaction(function () use ($data) {
                $nc = NonConformity::createWithFolio(array_merge($data, [
                    'issued_by' => auth()->id(),
                ]));
                $nc->log('creada', null, null, NcStage::Solicitada);

                return $nc;
            });

            $message = "NC {$nc->folio} registrada y enviada a Calidad.";
        }

        session()->flash('status', $message);

        $this->redirect(route('no-conformidad.show', $nc ?? $this->nc), navigate: true);
    }

    public function with(): array
    {
        $selectedDepartment = $this->department_id
            ? Department::with(['head', 'parent.head'])->find($this->department_id)
            : null;

        return [
            'processes'          => NcProcess::active()->get(),
            'subprocesses'       => NcSubprocess::active()->get(),
            'departments'        => Department::where('active', true)->orderBy('name')->get(),
            'origins'            => NcOrigin::cases(),
            'selectedDepartment' => $selectedDepartment,
            'returnReason'       => $this->nc?->stage === NcStage::DevueltaEmisor
                ? $this->nc->logs()->where('event', 'devuelta')->value('comment')
                : null,
        ];
    }
};