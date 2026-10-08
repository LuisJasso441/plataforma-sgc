<?php

use App\Enums\NcStatus;
use App\Models\Department;
use App\Models\NcProcess;
use App\Models\NonConformity;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('No Conformidad')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $process = '';
    public string $year = '';
    public string $department = '';

    /** Cualquier filtro regresa a la página 1 */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'process', 'year', 'department'], true)) {
            $this->resetPage();
        }
    }

    /** Filtros activos (se comparten con la exportación a Excel) */
    protected function filters(): array
    {
        return array_filter([
            'search'     => $this->search,
            'status'     => $this->status,
            'process'    => $this->process,
            'year'       => $this->year,
            'department' => $this->department,
        ], fn ($v) => $v !== '' && $v !== null);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'process', 'year', 'department']);
        $this->resetPage();
    }

    public function with(): array
    {
        $user = auth()->user();

        $ncs = NonConformity::query()
            ->visibleTo($user)
            ->with(['process', 'subprocess', 'department', 'leader', 'issuer'])
            ->filter($this->filters())
            ->orderByDesc('folio_year')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        // En el filtro, cada quien ve solo los departamentos que puede consultar
        $departments = Department::query()
            ->when(! $user->isAdmin() && ! $user->isCalidad(),
                fn ($q) => $q->whereIn('id', $user->visibleDepartmentIds()))
            ->orderBy('name')
            ->get();

        return [
            'exportUrl'   => route('no-conformidad.bitacora.export', $this->filters()),
            'ncs'         => $ncs,
            'departments' => $departments,
            'statuses'  => NcStatus::cases(),
            'processes' => NcProcess::active()->get(),
            'years'     => NonConformity::query()->visibleTo($user)
                ->distinct()->orderByDesc('folio_year')->pluck('folio_year'),
        ];
    }
};