<?php

use App\Enums\NcStatus;
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

    /** Cualquier filtro regresa a la página 1 */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'process', 'year'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'process', 'year']);
        $this->resetPage();
    }

    public function with(): array
    {
        $user = auth()->user();

        $ncs = NonConformity::query()
            ->visibleTo($user)
            ->with(['process', 'subprocess', 'department', 'leader', 'issuer'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('folio', 'like', "%{$this->search}%")
                      ->orWhere('initial_description', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->process, fn ($q) => $q->where('nc_process_id', $this->process))
            ->when($this->year, fn ($q) => $q->where('folio_year', $this->year))
            ->orderByDesc('folio_year')
            ->orderByDesc('folio_number')
            ->paginate(15);

        return [
            'ncs'       => $ncs,
            'statuses'  => NcStatus::cases(),
            'processes' => NcProcess::active()->get(),
            'years'     => NonConformity::query()->visibleTo($user)
                ->distinct()->orderByDesc('folio_year')->pluck('folio_year'),
        ];
    }
};