<?php

use App\Enums\NcStage;
use App\Models\NonConformity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('No Conformidad')] class extends Component
{
    public NonConformity $nc;

    // Diálogo "Aceptar"
    public ?int $leaderId = null;

    // Diálogo "Devolver"
    public string $returnReason = '';

    public function mount(NonConformity $nonConformity): void
    {
        Gate::authorize('view', $nonConformity);

        $this->nc = $nonConformity;

        // Líder sugerido: el jefe efectivo del departamento responsable
        $this->leaderId = $nonConformity->leader_id
            ?? $nonConformity->department->effectiveHead()?->id;
    }

    /**
     * Calidad acepta: se asigna líder y pasa a "Pendiente de reporte".
     */
    public function accept(): void
    {
        Gate::authorize('review', $this->nc);

        if ($this->nc->stage !== NcStage::Solicitada) {
            $this->backWith('error', 'Esta NC ya no está en espera de aceptación.');
            return;
        }

        $this->validate([
            'leaderId' => [
                'required',
                Rule::exists('users', 'id')
                    ->whereIn('role', ['user', 'calidad'])
                    ->where('active', true),
            ],
        ], [
            'leaderId.required' => 'Selecciona el líder de solución.',
        ]);

        $leader = User::find($this->leaderId);

        DB::transaction(function () use ($leader) {
            $from = $this->nc->stage;

            $this->nc->update([
                'stage'       => NcStage::PendienteReporte,
                'leader_id'   => $leader->id,
                'accepted_by' => auth()->id(),
                'accepted_at' => now(),
            ]);

            $this->nc->log('aceptada', "Líder de solución: {$leader->name}", $from, NcStage::PendienteReporte);
        });

        $this->backWith('status', "NC {$this->nc->folio} aceptada. Líder de solución: {$leader->name}.");
    }

    /**
     * Calidad devuelve la solicitud al emisor con un motivo.
     */
    public function returnToIssuer(): void
    {
        Gate::authorize('review', $this->nc);

        if ($this->nc->stage !== NcStage::Solicitada) {
            $this->backWith('error', 'Esta NC ya no está en espera de aceptación.');
            return;
        }

        $this->validate([
            'returnReason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], [
            'returnReason' => 'motivo',
        ]);

        DB::transaction(function () {
            $from = $this->nc->stage;

            $this->nc->update(['stage' => NcStage::DevueltaEmisor]);

            // El evento 'devuelta' es el que lee el formulario de corrección
            $this->nc->log('devuelta', trim($this->returnReason), $from, NcStage::DevueltaEmisor);
        });

        $this->backWith('status', "NC {$this->nc->folio} devuelta al emisor para corrección.");
    }

    /** Recarga la vista (cierra diálogos) con un mensaje flash */
    protected function backWith(string $type, string $message): void
    {
        session()->flash($type, $message);

        $this->redirect(route('no-conformidad.show', $this->nc), navigate: true);
    }

    public function with(): array
    {
        $this->nc->load([
            'process', 'subprocess', 'leader', 'issuer', 'acceptedBy',
            'department.head', 'department.parent.head',
        ]);

        return [
            'rd'              => $this->nc->report_data ?? [],
            'logs'            => $this->nc->logs()->with('user')->get(),
            'suggestedLeader' => $this->nc->department->effectiveHead(),
            'leaders'         => User::whereIn('role', ['user', 'calidad'])
                ->where('active', true)
                ->with('department')
                ->orderBy('name')
                ->get(),
        ];
    }
};