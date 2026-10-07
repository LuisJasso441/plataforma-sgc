<?php

use App\Models\NonConformity;
use App\Support\NcReportReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Datos del reporte')] class extends Component
{
    public NonConformity $nc;

    // Paso 3
    public array $equipo = [];

    // Paso 4
    public array $contencion = [];

    // Paso 6
    public string $causa_raiz = '';

    // Paso 7
    public string $similares_aplica = '';     // '' | si | no
    public string $similares_cuales = '';
    public string $cambios_opcion = '';       // '' | si | no | creacion
    public array $cambios_documentos = [];
    public string $cambios_otro = '';
    public string $efectividad_evidencia = '';
    public string $efectividad_plazo = '';

    public function mount(NonConformity $nonConformity): void
    {
        Gate::authorize('editReportData', $nonConformity);

        $this->nc = $nonConformity;
        $rd = $nonConformity->report_data ?? [];

        $this->equipo = collect($rd['equipo'] ?? [])->map(fn ($m) => [
            'nombre' => $m['nombre'] ?? '',
            'area'   => $m['area'] ?? '',
        ])->all() ?: [$this->emptyRow('equipo')];

        $this->contencion = collect($rd['contencion'] ?? [])->map(fn ($c) => [
            'actividad'    => $c['actividad'] ?? '',
            'responsable'  => $c['responsable'] ?? '',
            'fecha_inicio' => $c['fecha_inicio'] ?? '',
            'fecha_final'  => $c['fecha_final'] ?? '',
        ])->all() ?: [$this->emptyRow('contencion')];

        $this->causa_raiz            = $rd['causa_raiz'] ?? '';
        $this->similares_aplica      = $rd['procesos_similares']['aplica'] ?? '';
        $this->similares_cuales      = $rd['procesos_similares']['cuales'] ?? '';
        $this->cambios_opcion        = $rd['cambios_documentos']['opcion'] ?? '';
        $this->cambios_documentos    = $rd['cambios_documentos']['documentos'] ?? [];
        $this->cambios_otro          = $rd['cambios_documentos']['otro'] ?? '';
        $this->efectividad_evidencia = $rd['efectividad']['evidencia'] ?? '';
        $this->efectividad_plazo     = $rd['efectividad']['plazo'] ?? '';
    }

    protected function emptyRow(string $list): array
    {
        return $list === 'equipo'
            ? ['nombre' => '', 'area' => '']
            : ['actividad' => '', 'responsable' => '', 'fecha_inicio' => '', 'fecha_final' => ''];
    }

    public function addRow(string $list): void
    {
        if (in_array($list, ['equipo', 'contencion'], true)) {
            $this->{$list}[] = $this->emptyRow($list);
        }
    }

    public function removeRow(string $list, int $index): void
    {
        if (! in_array($list, ['equipo', 'contencion'], true)) {
            return;
        }

        unset($this->{$list}[$index]);
        $this->{$list} = array_values($this->{$list}) ?: [$this->emptyRow($list)];
    }

    protected function rules(): array
    {
        return [
            'equipo'                    => ['array', 'max:20'],
            'equipo.*.nombre'           => ['nullable', 'string', 'max:150'],
            'equipo.*.area'             => ['nullable', 'string', 'max:150'],
            'contencion'                => ['array', 'max:20'],
            'contencion.*.actividad'    => ['nullable', 'string', 'max:2000'],
            'contencion.*.responsable'  => ['nullable', 'string', 'max:150'],
            'contencion.*.fecha_inicio' => ['nullable', 'date'],
            'contencion.*.fecha_final'  => ['nullable', 'date', 'after_or_equal:contencion.*.fecha_inicio'],
            'causa_raiz'                => ['nullable', 'string', 'max:5000'],
            'similares_aplica'          => ['nullable', Rule::in(['', 'si', 'no'])],
            'similares_cuales'          => ['nullable', 'string', 'max:500'],
            'cambios_opcion'            => ['nullable', Rule::in(['', 'si', 'no', 'creacion'])],
            'cambios_documentos'        => ['array'],
            'cambios_documentos.*'      => [Rule::in(array_keys(NcReportReader::SHAPE_DOCUMENTOS))],
            'cambios_otro'              => ['nullable', 'string', 'max:255'],
            'efectividad_evidencia'     => ['nullable', 'string', 'max:5000'],
            'efectividad_plazo'         => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'contencion.*.fecha_final'  => 'fecha final',
            'contencion.*.fecha_inicio' => 'fecha inicio',
            'causa_raiz'                => 'causa raíz',
        ];
    }

    public function save(): void
    {
        Gate::authorize('editReportData', $this->nc);

        $this->validate();

        $equipo = collect($this->equipo)
            ->map(fn ($m) => ['nombre' => trim($m['nombre']), 'area' => trim($m['area'])])
            ->filter(fn ($m) => $m['nombre'] !== '' || $m['area'] !== '')
            ->values()->all();

        $contencion = collect($this->contencion)
            ->map(fn ($c) => [
                'actividad'    => trim($c['actividad']),
                'responsable'  => trim($c['responsable']),
                'fecha_inicio' => $c['fecha_inicio'] ?: null,
                'fecha_final'  => $c['fecha_final'] ?: null,
            ])
            ->filter(fn ($c) => $c['actividad'] !== '' || $c['responsable'] !== '' || $c['fecha_inicio'])
            ->values()->all();

        DB::transaction(function () use ($equipo, $contencion) {
            // Se conservan las claves que no se editan aquí (ej. 'acciones')
            $this->nc->update([
                'report_data' => array_merge($this->nc->report_data ?? [], [
                    'equipo'             => $equipo,
                    'contencion'         => $contencion,
                    'causa_raiz'         => trim($this->causa_raiz),
                    'procesos_similares' => [
                        'aplica' => $this->similares_aplica ?: null,
                        'cuales' => trim($this->similares_cuales),
                    ],
                    'cambios_documentos' => [
                        'opcion'     => $this->cambios_opcion ?: null,
                        'documentos' => array_values($this->cambios_documentos),
                        'otro'       => trim($this->cambios_otro),
                    ],
                    'efectividad'        => [
                        'evidencia' => trim($this->efectividad_evidencia),
                        'plazo'     => trim($this->efectividad_plazo),
                    ],
                ]),
            ]);

            $this->nc->log('reporte_editado');
        });

        session()->flash('status', "Datos del reporte de {$this->nc->folio} actualizados.");

        $this->redirect(route('no-conformidad.show', $this->nc), navigate: true);
    }

    public function with(): array
    {
        return [
            'documentCatalog' => array_keys(NcReportReader::SHAPE_DOCUMENTOS),
        ];
    }
};