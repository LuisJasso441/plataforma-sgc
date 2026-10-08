@php
    use App\Enums\NcOrigin;
    use App\Enums\NcStage;

    $empty = 'Se llenará al subir el reporte.';
    $docsCatalog = ['Procedimiento', 'Formato', 'Anexos', 'Instructivo de Trabajo', 'Plan Control / POT', 'Alerta', 'Ayuda Visual'];
@endphp

<div>
    @if (session('status'))
        <flux:callout variant="success" class="mb-4" icon="check-circle" :heading="session('status')" />
    @endif
    @if (session('error'))
        <flux:callout variant="danger" class="mb-4" icon="exclamation-triangle" :heading="session('error')" />
    @endif

    {{-- Encabezado --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $nc->folio }}</flux:heading>
                <flux:badge size="sm" :color="$nc->status->color()">{{ $nc->status->label() }}</flux:badge>
                <flux:badge size="sm" color="zinc">{{ $nc->stage->label() }}</flux:badge>
            </div>
            <flux:subheading>Reporte de No Conformidad y Acción Correctiva</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button variant="ghost" icon="arrow-left" :href="route('no-conformidad.index')" wire:navigate>
                Bitácora
            </flux:button>

            @can('correct', $nc)
                <flux:button icon="pencil-square" :href="route('no-conformidad.edit', $nc)" wire:navigate>
                    {{ $nc->stage === NcStage::DevueltaEmisor ? 'Corregir' : 'Editar' }}
                </flux:button>
            @endcan

            @can('editReportData', $nc)
                <flux:button icon="document-text" :href="route('no-conformidad.report.edit', $nc)" wire:navigate>
                    Editar datos del reporte
                </flux:button>
            @endcan

            @can('review', $nc)
                @if ($nc->stage === NcStage::Solicitada)
                    <flux:modal.trigger name="devolver-nc">
                        <flux:button icon="arrow-uturn-left">Devolver</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger name="aceptar-nc">
                        <flux:button variant="primary" icon="check">Aceptar</flux:button>
                    </flux:modal.trigger>
                @endif
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- ═══════════ Columna principal: el reporte ═══════════ --}}
        <div class="flex flex-col gap-6 lg:col-span-2">

            {{-- Archivo del reporte (descarga, subida y revisión) --}}
            @if ($nc->stage->reached(NcStage::PendienteReporte))
                <livewire:no-conformidad.report-panel :nc="$nc" :key="'report-panel-'.$nc->id" />
            @endif

            {{-- Paso 1: Datos --}}
            <x-nc.step number="1" title="Datos" subtitle="Indicar el tipo de acción, quién emite y a quién se le solicita">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase text-zinc-500">a. Datos generales</div>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 text-sm">
                            <dt class="text-zinc-500">Fecha:</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">{{ $nc->trigger_date?->format('d/m/Y') ?? '—' }}</dd>
                            <dt class="text-zinc-500">Folio:</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $nc->folio }}</dd>
                            <dt class="text-zinc-500">Proceso:</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">
                                {{ $nc->process->name }}{{ $nc->subprocess ? ' / ' . $nc->subprocess->name : '' }}
                            </dd>
                            <dt class="text-zinc-500">Líder de Solución:</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">
                                @if ($nc->leader)
                                    {{ $nc->leader->name }}
                                @else
                                    <span class="text-zinc-500">Se asigna al aceptar
                                        @if ($suggestedLeader) (sugerido: {{ $suggestedLeader->name }}) @endif
                                    </span>
                                @endif
                            </dd>
                        </dl>
                    </div>

                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase text-zinc-500">b. Origen de acción</div>
                        <div class="flex flex-col gap-1.5">
                            @foreach (NcOrigin::cases() as $origin)
                                <x-nc.check :checked="$nc->origin === $origin">{{ $origin->label() }}</x-nc.check>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Datos propios de la plataforma --}}
                <div class="mt-4 grid grid-cols-1 gap-3 border-t border-zinc-200 pt-3 text-xs dark:border-zinc-700 sm:grid-cols-3">
                    <div>
                        <div class="text-zinc-500">Quién emite</div>
                        <div class="text-zinc-900 dark:text-zinc-100">{{ $nc->issuer->name }} · {{ $nc->created_at->format('d/m/Y') }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500">Departamento responsable</div>
                        <div class="text-zinc-900 dark:text-zinc-100">{{ $nc->department->name }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500">Aceptada por</div>
                        <div class="text-zinc-900 dark:text-zinc-100">
                            {{ $nc->accepted_at ? ($nc->acceptedBy?->name ?? '—') . ' · ' . $nc->accepted_at->format('d/m/Y') : '—' }}
                        </div>
                    </div>
                    @if ($nc->parent)
                        <div class="sm:col-span-3">
                            <span class="text-zinc-500">Abierta por no efectividad de</span>
                            <a href="{{ route('no-conformidad.show', $nc->parent) }}" wire:navigate
                                class="font-medium text-sky-600 hover:underline dark:text-sky-400">{{ $nc->parent->folio }}</a>
                        </div>
                    @endif
                </div>
            </x-nc.step>

            {{-- Paso 2: Descripción del problema --}}
            <x-nc.step number="2" title="Descripción del problema"
                subtitle="Detalle del problema con: Qué sucedió, cantidad, operación, fecha, etc. (QUÉ, QUIÉN, CÓMO, CUÁNDO, DÓNDE, CUÁNTO, POR QUÉ, ETC.)">
                <div class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $nc->description ?? $nc->initial_description }}</div>

                @if ($nc->description && $nc->description !== $nc->initial_description)
                    <details class="mt-3 text-xs text-zinc-500">
                        <summary class="cursor-pointer">Ver descripción original de la solicitud</summary>
                        <div class="mt-1 whitespace-pre-line">{{ $nc->initial_description }}</div>
                    </details>
                @endif
            </x-nc.step>

            {{-- Pasos 3 a 8: solo desde que se sube el reporte --}}
            @if ($nc->stage->reached(NcStage::ReporteEnRevision))

            {{-- Paso 3: Equipo de trabajo --}}
            <x-nc.step number="3" title="Equipo de trabajo" subtitle="El equipo debe ser multidisciplinario en la medida de lo posible">
                @if (! empty($rd['equipo']))
                    <div class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        @foreach ($rd['equipo'] as $member)
                            <div class="flex justify-between gap-3 border-b border-zinc-100 pb-1 dark:border-zinc-800">
                                <span class="text-zinc-900 dark:text-zinc-100">{{ $member['nombre'] ?: '—' }}</span>
                                <span class="text-zinc-500">{{ $member['area'] ?: '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-sm text-zinc-500">{{ $empty }}</div>
                @endif
            </x-nc.step>

            {{-- Paso 4: Acciones de contención --}}
            <x-nc.step number="4" title="Acciones de contención" subtitle="Defina las acciones inmediatas de contención (24 horas máximo)">
                @if (! empty($rd['contencion']))
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs text-zinc-500">
                                <tr>
                                    <th class="py-1 pr-3 font-medium">Actividad / Evidencia de realización</th>
                                    <th class="py-1 pr-3 font-medium">Responsable</th>
                                    <th class="py-1 pr-3 font-medium">Fecha inicio</th>
                                    <th class="py-1 font-medium">Fecha final</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($rd['contencion'] as $item)
                                    <tr class="align-top">
                                        <td class="py-1.5 pr-3 whitespace-pre-line">{{ $item['actividad'] ?: '—' }}</td>
                                        <td class="py-1.5 pr-3">{{ $item['responsable'] ?: '—' }}</td>
                                        <td class="py-1.5 pr-3 whitespace-nowrap">{{ $item['fecha_inicio'] ? \Illuminate\Support\Carbon::parse($item['fecha_inicio'])->format('d/m/Y') : '—' }}</td>
                                        <td class="py-1.5 whitespace-nowrap">{{ $item['fecha_final'] ? \Illuminate\Support\Carbon::parse($item['fecha_final'])->format('d/m/Y') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-sm text-zinc-500">{{ $empty }}</div>
                @endif
            </x-nc.step>

            {{-- Paso 5: Análisis de causa raíz --}}
            <x-nc.step number="5" title="Análisis de causa raíz">
                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    El análisis se realiza en las hojas de herramientas del reporte (5 P's, Ishikawa, lluvia de ideas).
                    Consúltalo en el archivo vigente del reporte.
                </div>
            </x-nc.step>

            {{-- Paso 6: Resultado del análisis de causa raíz --}}
            <x-nc.step number="6" title="Resultado del análisis de causa raíz"
                subtitle="Describa el origen del problema o sus principales causas probables">
                @if (! empty($rd['causa_raiz']))
                    <div class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $rd['causa_raiz'] }}</div>
                @else
                    <div class="text-sm text-zinc-500">{{ $empty }}</div>
                @endif
            </x-nc.step>

            {{-- Paso 7: Acciones definitivas --}}
            <x-nc.step number="7" title="Acciones definitivas" subtitle="Revisar el impacto del problema en otros productos y procesos">
                <div class="flex flex-col gap-5">
                    @if ($rd)
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            {{-- Procesos similares --}}
                            <div>
                                <div class="mb-2 text-xs font-semibold text-zinc-500">¿Aplica a procesos similares?</div>
                                <div class="flex gap-4">
                                    <x-nc.check :checked="($rd['procesos_similares']['aplica'] ?? null) === 'si'">SI</x-nc.check>
                                    <x-nc.check :checked="($rd['procesos_similares']['aplica'] ?? null) === 'no'">NO</x-nc.check>
                                </div>
                                @if (! empty($rd['procesos_similares']['cuales']))
                                    <div class="mt-2 text-sm">
                                        <span class="text-zinc-500">¿Cuáles?</span>
                                        <span class="text-zinc-900 dark:text-zinc-100">{{ $rd['procesos_similares']['cuales'] }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Cambios en documentos --}}
                            <div>
                                <div class="mb-2 text-xs font-semibold text-zinc-500">¿Requiere cambios en documentos?</div>
                                <div class="flex flex-wrap gap-4">
                                    @foreach (['si' => 'SI', 'no' => 'NO', 'creacion' => 'CREACIÓN'] as $value => $label)
                                        <x-nc.check :checked="($rd['cambios_documentos']['opcion'] ?? null) === $value">{{ $label }}</x-nc.check>
                                    @endforeach
                                </div>
                                <div class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                    @foreach ($docsCatalog as $doc)
                                        <x-nc.check :checked="in_array($doc, $rd['cambios_documentos']['documentos'] ?? [], true)">{{ $doc }}</x-nc.check>
                                    @endforeach
                                </div>
                                @if (! empty($rd['cambios_documentos']['otro']))
                                    <div class="mt-2 text-sm">
                                        <span class="text-zinc-500">Otro documento:</span>
                                        <span class="text-zinc-900 dark:text-zinc-100">{{ $rd['cambios_documentos']['otro'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Actividades: captura / evidencias (plataforma) o lo leído del reporte --}}
                    @if ($nc->stage->reached(NcStage::CapturaAcciones))
                        <livewire:no-conformidad.actions-panel :nc="$nc" :key="'actions-panel-'.$nc->id" />
                    @elseif (! empty($rd['acciones']))
                        <div>
                            <div class="mb-2 text-xs font-semibold text-zinc-500">
                                Actividades que eliminarán el problema de raíz (leídas del reporte)
                            </div>
                            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-zinc-50 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        <tr>
                                            <th class="px-3 py-2 font-medium">Número</th>
                                            <th class="px-3 py-2 font-medium">Actividad / Evidencia de realización</th>
                                            <th class="px-3 py-2 font-medium">Responsable</th>
                                            <th class="px-3 py-2 font-medium">Fecha compromiso</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                        @foreach ($rd['acciones'] as $action)
                                            <tr class="align-top">
                                                <td class="px-3 py-2">{{ $action['numero'] }}</td>
                                                <td class="px-3 py-2 whitespace-pre-line">{{ $action['actividad'] ?: '—' }}</td>
                                                <td class="px-3 py-2">{{ $action['responsable'] ?: '—' }}</td>
                                                <td class="px-3 py-2 whitespace-nowrap">
                                                    {{ $action['fecha_compromiso'] ? \Illuminate\Support\Carbon::parse($action['fecha_compromiso'])->format('d/m/Y') : '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="text-sm text-zinc-500">{{ $empty }}</div>
                    @endif

                    {{-- Efectividad de acciones (siempre visible: es un campo a llenar del formato) --}}
                    @php
                        $efEvidencia = $rd['efectividad']['evidencia'] ?? '';
                        $efPlazo     = $rd['efectividad']['plazo'] ?? '';
                    @endphp
                    <div class="grid grid-cols-1 overflow-hidden rounded-lg border border-zinc-200 text-sm dark:border-zinc-700 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                        <div class="border-b border-zinc-200 p-3 dark:border-zinc-700 md:border-b-0 md:border-r">
                            <div class="text-xs font-semibold text-zinc-500">
                                EFECTIVIDAD DE ACCIONES: mencione cómo se demostrará (con qué evidencia) que el problema ha sido eliminado y no será recurrente
                            </div>
                            @if ($efEvidencia !== '')
                                <div class="mt-1 whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $efEvidencia }}</div>
                            @else
                                <div class="mt-1 text-amber-600 dark:text-amber-400">Pendiente de llenar</div>
                            @endif
                        </div>
                        <div class="p-3">
                            <div class="text-xs font-semibold text-zinc-500">Plazo / fecha para verificación de efectividad de las acciones</div>
                            @if ($efPlazo !== '')
                                <div class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $efPlazo }}</div>
                            @else
                                <div class="mt-1 text-amber-600 dark:text-amber-400">Pendiente de llenar</div>
                            @endif
                        </div>
                    </div>
                </div>
            </x-nc.step>

            {{-- Paso 8: Seguimiento de efectividad --}}
            <x-nc.step number="8" title="Seguimiento de efectividad de acciones"
                subtitle="Verificación de efectividad de las acciones tomadas para la eliminación del problema">
                @if ($nc->stage->reached(NcStage::EnVerificacion))
                    <livewire:no-conformidad.verification-panel :nc="$nc" :key="'verification-panel-'.$nc->id" />
                @else
                    <div class="text-sm text-zinc-500">
                        Se realiza cuando todas las acciones definitivas estén validadas
                        @if ($nc->verification_date)
                            · fecha de verificación: {{ $nc->verification_date->format('d/m/Y') }}
                        @endif
                    </div>
                @endif
            </x-nc.step>

            @else
                <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-center text-sm text-zinc-500 dark:border-zinc-700">
                    Los pasos 3 a 8 del reporte se mostrarán cuando el líder de solución suba el reporte de la reunión.
                </div>
            @endif
        </div>

        {{-- ═══════════ Columna lateral ═══════════ --}}
        <div class="flex flex-col gap-6">
            {{-- Fechas --}}
            <div class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">Seguimiento de fechas</flux:heading>

                <dl class="grid grid-cols-1 gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Fecha de disparo</dt>
                        <dd>{{ $nc->trigger_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Implementación</dt>
                        <dd>{{ $nc->commitment_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Verificación de efectividad</dt>
                        <dd>{{ $nc->verification_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Cierre real</dt>
                        <dd>{{ $nc->actual_close_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Línea de tiempo --}}
            <div class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">Línea de tiempo</flux:heading>

                <ol class="relative ms-2 border-s border-zinc-200 dark:border-zinc-700">
                    @forelse ($logs as $log)
                        <li class="mb-5 ms-4 last:mb-0">
                            <div class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full border-2 border-white bg-zinc-400 dark:border-zinc-800"></div>
                            <div class="text-xs text-zinc-500">
                                {{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->user?->name ?? 'Sistema' }}
                            </div>
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $log->eventLabel() }}</div>
                            @if ($log->comment)
                                <div class="mt-1 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-400">{{ $log->comment }}</div>
                            @endif
                        </li>
                    @empty
                        <li class="ms-4 text-sm text-zinc-500">Sin movimientos.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>

    {{-- Diálogo: Aceptar --}}
    @can('review', $nc)
        <flux:modal name="aceptar-nc" class="md:w-[32rem]">
            <form wire:submit="accept" class="space-y-6">
                <div>
                    <flux:heading size="lg">Aceptar {{ $nc->folio }}</flux:heading>
                    <flux:text class="mt-2">
                        La NC pasará a <strong>Pendiente de reporte</strong>: el líder convocará la reunión
                        multidisciplinaria y subirá el reporte.
                    </flux:text>
                </div>

                @unless ($suggestedLeader)
                    <flux:callout variant="warning" icon="exclamation-triangle"
                        heading="{{ $nc->department->name }} no tiene jefe asignado; selecciona el líder." />
                @endunless

                <flux:select wire:model="leaderId" label="Líder de solución" placeholder="Selecciona un usuario...">
                    @foreach ($leaders as $leader)
                        <flux:select.option :value="$leader->id">
                            {{ $leader->name }} — {{ $leader->department?->name ?? 'Sin depto.' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Aceptar NC</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Diálogo: Devolver --}}
        <flux:modal name="devolver-nc" class="md:w-[32rem]">
            <form wire:submit="returnToIssuer" class="space-y-6">
                <div>
                    <flux:heading size="lg">Devolver {{ $nc->folio }} al emisor</flux:heading>
                    <flux:text class="mt-2">
                        {{ $nc->issuer->name }} verá este motivo y podrá corregir y reenviar la solicitud.
                    </flux:text>
                </div>

                <flux:textarea wire:model="returnReason" label="Motivo de la devolución" rows="4"
                    placeholder="Indica qué debe corregirse..." />

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">Devolver</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>