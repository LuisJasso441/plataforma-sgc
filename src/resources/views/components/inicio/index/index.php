<?php

use App\Enums\NcActionStatus;
use App\Enums\NcStage;
use App\Enums\NcStatus;
use App\Models\Department;
use App\Models\NcAction;
use App\Models\NonConformity;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Inicio')] class extends Component
{
    /**
     * Categorías del tablero. El orden es el de los segmentos de la gráfica
     * (validado para distinguirse con daltonismo); el color sigue al estatus.
     */
    public const CATEGORIES = [
        'cerrada'      => ['label' => 'Cerradas',        'color' => '#16a34a'],
        'abierta'      => ['label' => 'Abiertas',        'color' => '#0284c7'],
        'verificacion' => ['label' => 'En verificación', 'color' => '#7c3aed'],
        'vencida'      => ['label' => 'Vencidas',        'color' => '#f59e0b'],
        'no_efectiva'  => ['label' => 'No efectivas',    'color' => '#dc2626'],
    ];

    /** Orden de lectura de las tarjetas */
    public const TILE_ORDER = ['abierta', 'verificacion', 'vencida', 'cerrada', 'no_efectiva'];

    public string $year = '';

    public function mount(): void
    {
        $this->year = (string) now()->year;
    }

    protected function category(NonConformity $nc): string
    {
        return match (true) {
            $nc->status === NcStatus::Cerrada      => 'cerrada',
            $nc->status === NcStatus::NoEfectiva   => 'no_efectiva',
            $nc->status === NcStatus::Vencida      => 'vencida',
            $nc->stage === NcStage::EnVerificacion => 'verificacion',
            default                                => 'abierta',
        };
    }

    public function with(): array
    {
        $user = auth()->user();
        $hour = now()->hour;

        $base = [
            'user'      => $user,
            'firstName' => Str::before($user->name, ' '),
            'greeting'  => $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches'),
            'today'     => Str::ucfirst(now()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
        ];

        // Soporte: tablero limpio, sin registros de NC
        if ($user->isAdmin()) {
            return $base + [
                'mode'    => 'soporte',
                'support' => [
                    'activos'       => User::where('active', true)->count(),
                    'inactivos'     => User::where('active', false)->count(),
                    'departamentos' => Department::where('active', true)->count(),
                ],
            ];
        }

        if (! $user->isCalidad() && ! $user->canRead('no-conformidad')) {
            return $base + ['mode' => 'sin_acceso'];
        }

        // ── NC del periodo, con la misma visibilidad que la bitácora ──
        $ncs = NonConformity::query()
            ->visibleTo($user)
            ->when($this->year, fn ($q) => $q->where('folio_year', $this->year))
            ->with('department:id,name')
            ->get(['id', 'department_id', 'stage', 'status', 'created_at', 'closed_at']);

        $rows   = $ncs->map(fn (NonConformity $nc) => ['nc' => $nc, 'cat' => $this->category($nc)]);
        $counts = collect(self::CATEGORIES)->map(fn ($c, $key) => $rows->where('cat', $key)->count());

        // Por departamento (mayor a menor)
        $byDepartment = $rows
            ->groupBy(fn ($r) => $r['nc']->department_id)
            ->map(fn ($group) => [
                'department' => $group->first()['nc']->department,
                'total'      => $group->count(),
                'segments'   => collect(self::CATEGORIES)
                    ->map(fn ($c, $key) => $group->where('cat', $key)->count())
                    ->filter(),
            ])
            ->sortByDesc('total')
            ->values();

        // Efectividad: cerradas / (cerradas + no efectivas)
        $concluded     = $counts['cerrada'] + $counts['no_efectiva'];
        $effectiveness = $concluded ? (int) round($counts['cerrada'] / $concluded * 100) : null;

        // Tiempo promedio de cierre: del registro a la verificación de efectividad
        $days    = $ncs->filter(fn ($nc) => $nc->closed_at)->map(fn ($nc) => $nc->created_at->diffInDays($nc->closed_at));
        $avgDays = $days->isNotEmpty() ? round($days->avg(), 1) : null;

        // ── Requiere atención (no depende del año) ──
        $limit = today()->addDays(7);

        $actions = NcAction::query()
            ->whereHas('nonConformity', fn ($q) => $q->visibleTo($user)->where('stage', NcStage::EnImplementacion->value))
            ->where('status', '!=', NcActionStatus::Validada->value)
            ->whereDate('commitment_date', '<=', $limit)
            ->with('nonConformity.department')
            ->get()
            ->map(fn (NcAction $a) => [
                'type'   => $a->commitment_date->lt(today()) ? 'vencida' : 'compromiso',
                'nc'     => $a->nonConformity,
                'detail' => "Acción {$a->number}: " . Str::limit($a->activity, 70),
                'date'   => $a->commitment_date,
            ]);

        $verifications = NonConformity::query()
            ->visibleTo($user)
            ->where('stage', NcStage::EnVerificacion->value)
            ->whereDate('verification_date', '<=', $limit)
            ->with('department')
            ->get()
            ->map(fn (NonConformity $nc) => [
                'type'   => 'verificacion',
                'nc'     => $nc,
                'detail' => 'Verificación de efectividad',
                'date'   => $nc->verification_date,
            ]);

        $years = NonConformity::query()->visibleTo($user)
            ->distinct()->orderByDesc('folio_year')->pluck('folio_year')
            ->push(now()->year)->unique()->sortDesc()->values();

        return $base + [
            'mode'          => 'nc',
            'categories'    => self::CATEGORIES,
            'tileOrder'     => self::TILE_ORDER,
            'counts'        => $counts,
            'total'         => $ncs->count(),
            'byDepartment'  => $byDepartment,
            'maxTotal'      => max(1, (int) $byDepartment->max('total')),
            'concluded'     => $concluded,
            'effectiveness' => $effectiveness,
            'avgDays'       => $avgDays,
            'attention'     => $actions->concat($verifications)->sortBy('date')->take(10)->values(),
            'years'         => $years,
        ];
    }
};