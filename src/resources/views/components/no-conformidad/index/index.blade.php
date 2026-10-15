<div>
    @if (session('status'))
        <flux:callout variant="success" class="mb-4" icon="check-circle" :heading="session('status')" />
    @endif
    @if (session('error'))
        <flux:callout variant="danger" class="mb-4" icon="exclamation-triangle" :heading="session('error')" />
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">No Conformidad</flux:heading>
            <flux:subheading>Bitácora de no conformidades y acciones correctivas</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            {{-- Sin wire:navigate: es una descarga de archivo --}}
            <flux:button icon="arrow-down-tray" :href="$exportUrl">
                Exportar bitácora
            </flux:button>

            @can('create', App\Models\NonConformity::class)
                <flux:button variant="primary" icon="plus" :href="route('no-conformidad.create')" wire:navigate>
                    Nueva NC
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-6">
        <div class="md:col-span-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por folio o descripción..."
                icon="magnifying-glass"
            />
        </div>

        <flux:select wire:model.live="status" placeholder="Estatus">
            <flux:select.option value="">Todos los estatus</flux:select.option>
            @foreach ($statuses as $s)
                <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="department" placeholder="Departamento">
            <flux:select.option value="">Todos los departamentos</flux:select.option>
            @foreach ($departments as $d)
                <flux:select.option :value="$d->id">{{ $d->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="process" placeholder="Proceso">
            <flux:select.option value="">Todos los procesos</flux:select.option>
            @foreach ($processes as $p)
                <flux:select.option :value="$p->id">{{ $p->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex gap-2">
            <flux:select wire:model.live="year" placeholder="Año">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($years as $y)
                    <flux:select.option :value="$y">{{ $y }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters" tooltip="Limpiar filtros" />
        </div>
    </div>

    {{-- Renglones desplegables (estado en el navegador; se reinicia al cambiar filtros o página) --}}
    <div wire:key="bitacora-{{ $tableKey }}"
        x-data="{
            open: [],
            toggle(id) { this.open = this.open.includes(id) ? this.open.filter(i => i !== id) : [...this.open, id] },
        }">

        @if ($ncs->isNotEmpty())
            <div class="mb-2 flex justify-end gap-1">
                <flux:button size="xs" variant="ghost" icon="chevron-double-down"
                    x-on:click="open = {{ Js::from($ncs->pluck('id')) }}">
                    Expandir todo
                </flux:button>
                <flux:button size="xs" variant="ghost" icon="chevron-double-up" x-on:click="open = []">
                    Contraer todo
                </flux:button>
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                    <tr>
                        <th class="w-10 px-2 py-3"><span class="sr-only">Detalle</span></th>
                        <th class="px-4 py-3 font-medium">Folio</th>
                        <th class="px-4 py-3 font-medium">Proceso / Sub-proceso</th>
                        <th class="px-4 py-3 font-medium">Departamento / Líder</th>
                        <th class="px-4 py-3 font-medium min-w-64">Descripción</th>
                        <th class="px-4 py-3 font-medium">Quién emite</th>
                        <th class="px-4 py-3 font-medium">Fechas</th>
                        <th class="px-4 py-3 font-medium">Estatus</th>
                        <th class="px-4 py-3 font-medium text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($ncs as $nc)
                        {{-- Renglón principal --}}
                        <tr wire:key="nc-{{ $nc->id }}" class="align-top transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                            :class="open.includes({{ $nc->id }}) && 'bg-verden-green/5 dark:bg-verden-mint/5'">
                            <td class="px-2 py-3">
                                <button type="button" x-on:click="toggle({{ $nc->id }})"
                                    :aria-expanded="open.includes({{ $nc->id }})" aria-label="Ver detalle de {{ $nc->folio }}"
                                    class="flex size-7 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200">
                                    <flux:icon.chevron-right variant="mini" class="transition-transform duration-200"
                                        ::class="open.includes({{ $nc->id }}) && 'rotate-90'" />
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('no-conformidad.show', $nc) }}" wire:navigate
                                    class="font-medium text-sky-600 dark:text-sky-400 whitespace-nowrap hover:underline">
                                    {{ $nc->folio }}
                                </a>
                                <div class="text-zinc-500 text-xs whitespace-nowrap">{{ $nc->stage->label() }}</div>
                                @if ($nc->stage === App\Enums\NcStage::EnVerificacion && $nc->verification_date && ! $nc->verification_date->isFuture())
                                    <flux:badge size="sm" color="amber" class="mt-1">Verificar</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                <div>{{ $nc->process->name }}</div>
                                <div class="text-zinc-500 text-xs">{{ $nc->subprocess?->name ?? 'N. A.' }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                <flux:badge size="sm" :color="$nc->department->badgeColor()">{{ $nc->department->name }}</flux:badge>
                                <div class="mt-1 text-zinc-500 text-xs">{{ $nc->leader?->name ?? 'Sin líder asignado' }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ \Illuminate\Support\Str::limit($nc->description ?? $nc->initial_description, 120) }}
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                <div>{{ $nc->issuer->name }}</div>
                                <div class="text-zinc-500 text-xs">{{ $nc->created_at->format('d/m/Y') }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                <div>Disparo: {{ $nc->trigger_date?->format('d/m/Y') ?? '—' }}</div>
                                <div>Implementación: {{ $nc->commitment_date?->format('d/m/Y') ?? '—' }}</div>
                                <div>Verificación: {{ $nc->verification_date?->format('d/m/Y') ?? '—' }}</div>
                                <div>Cierre real: {{ $nc->actual_close_date?->format('d/m/Y') ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$nc->status->color()">{{ $nc->status->label() }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="sm" variant="ghost" icon="eye"
                                    :href="route('no-conformidad.show', $nc)" wire:navigate>
                                    Ver
                                </flux:button>

                                {{-- En la tabla solo se muestra "Corregir" al emisor cuando su NC fue devuelta.
                                     Calidad edita desde la vista de detalle. --}}
                                @if ($nc->stage === App\Enums\NcStage::DevueltaEmisor && $nc->issued_by === auth()->id())
                                    <flux:button size="sm" variant="ghost" icon="pencil-square"
                                        :href="route('no-conformidad.edit', $nc)" wire:navigate>
                                        Corregir
                                    </flux:button>
                                @endif
                            </td>
                        </tr>

                        {{-- Detalle desplegable --}}
                        <tr wire:key="nc-detalle-{{ $nc->id }}" x-show="open.includes({{ $nc->id }})" style="display: none">
                            <td colspan="9" class="border-l-2 border-accent bg-zinc-50/70 px-6 py-5 dark:bg-white/[0.03]">
                                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                                    {{-- Datos generales --}}
                                    <div>
                                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Datos generales</div>
                                        <dl class="space-y-1.5 text-xs">
                                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">Origen</dt>
                                                <dd class="text-zinc-800 dark:text-zinc-200">{{ $nc->origin?->label() ?? '—' }}</dd></div>
                                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">Proceso</dt>
                                                <dd class="text-zinc-800 dark:text-zinc-200">{{ $nc->process->name }} · {{ $nc->subprocess?->name ?? 'N. A.' }}</dd></div>
                                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">Procesos afectados</dt>
                                                <dd class="text-zinc-800 dark:text-zinc-200">{{ $nc->affectedProcesses->pluck('name')->implode(', ') ?: 'N. A.' }}</dd></div>
                                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">Emitió</dt>
                                                <dd class="text-zinc-800 dark:text-zinc-200">{{ $nc->issuer->name }} · {{ $nc->created_at->format('d/m/Y') }}</dd></div>
                                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">Aceptó</dt>
                                                <dd class="text-zinc-800 dark:text-zinc-200">
                                                    {{ $nc->acceptedBy ? $nc->acceptedBy->name . ' · ' . $nc->accepted_at?->format('d/m/Y') : '—' }}
                                                </dd></div>
                                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">Líder</dt>
                                                <dd class="text-zinc-800 dark:text-zinc-200">{{ $nc->leader?->name ?? 'Sin líder asignado' }}</dd></div>
                                            @if ($nc->parent)
                                                <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">Viene de</dt>
                                                    <dd><a href="{{ route('no-conformidad.show', $nc->parent) }}" wire:navigate
                                                        class="font-medium text-sky-600 hover:underline dark:text-sky-400">{{ $nc->parent->folio }}</a>
                                                        <span class="text-zinc-500">(no efectiva)</span></dd></div>
                                            @endif
                                            @foreach ($nc->reopenings as $reopening)
                                                <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">Reabierta como</dt>
                                                    <dd><a href="{{ route('no-conformidad.show', $reopening) }}" wire:navigate
                                                        class="font-medium text-sky-600 hover:underline dark:text-sky-400">{{ $reopening->folio }}</a></dd></div>
                                            @endforeach
                                        </dl>
                                    </div>

                                    {{-- Descripción --}}
                                    <div class="lg:col-span-2">
                                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Descripción</div>
                                        <p class="whitespace-pre-line text-sm text-zinc-800 dark:text-zinc-200">{{ $nc->description ?? $nc->initial_description }}</p>
                                        @if ($nc->description && $nc->initial_description && $nc->description !== $nc->initial_description)
                                            <div class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Descripción inicial (solicitud)</div>
                                            <p class="mt-1 whitespace-pre-line text-xs text-zinc-600 dark:text-zinc-400">{{ $nc->initial_description }}</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Acciones --}}
                                <div class="mt-6">
                                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                                        Acciones ({{ $nc->actions->count() }})
                                    </div>
                                    @if ($nc->actions->isEmpty())
                                        <div class="text-xs text-zinc-500">Sin acciones capturadas todavía.</div>
                                    @else
                                        <div class="overflow-x-auto rounded-md border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                                            <table class="w-full text-xs">
                                                <thead class="text-zinc-500">
                                                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                                        <th class="px-3 py-2 text-left font-medium">#</th>
                                                        <th class="px-3 py-2 text-left font-medium min-w-72">Actividad</th>
                                                        <th class="px-3 py-2 text-left font-medium">Responsable</th>
                                                        <th class="px-3 py-2 text-left font-medium whitespace-nowrap">Compromiso</th>
                                                        <th class="px-3 py-2 text-left font-medium whitespace-nowrap">Fecha real</th>
                                                        <th class="px-3 py-2 text-left font-medium">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                                    @foreach ($nc->actions as $action)
                                                        <tr class="align-top">
                                                            <td class="px-3 py-2 text-zinc-500">{{ $action->number }}</td>
                                                            <td class="px-3 py-2 whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $action->activity }}</td>
                                                            <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $action->responsible }}</td>
                                                            <td class="px-3 py-2 whitespace-nowrap {{ $action->isOverdue() ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                                                {{ $action->commitment_date?->format('d/m/Y') ?? '—' }}
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-zinc-700 dark:text-zinc-300">{{ $action->actual_end_date?->format('d/m/Y') ?? '—' }}</td>
                                                            <td class="px-3 py-2">
                                                                <flux:badge size="sm" :color="$action->status->color()">{{ $action->status->label() }}</flux:badge>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>

                                {{-- Seguimiento y cierre --}}
                                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                                    <div>
                                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Seguimiento y cierre</div>
                                        <dl class="space-y-1.5 text-xs">
                                            @foreach ([
                                                'Disparo'        => $nc->trigger_date,
                                                'Implementación' => $nc->commitment_date,
                                                'Verificación'   => $nc->verification_date,
                                                'Cierre real'    => $nc->actual_close_date,
                                            ] as $label => $date)
                                                <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">{{ $label }}</dt>
                                                    <dd class="text-zinc-800 dark:text-zinc-200">{{ $date?->format('d/m/Y') ?? '—' }}</dd></div>
                                            @endforeach
                                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">¿Fue efectiva?</dt>
                                                <dd>
                                                    @if (is_null($nc->is_effective))
                                                        <span class="text-zinc-500">—</span>
                                                    @else
                                                        <flux:badge size="sm" :color="$nc->is_effective ? 'green' : 'red'">{{ $nc->is_effective ? 'Sí' : 'No' }}</flux:badge>
                                                    @endif
                                                </dd></div>
                                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-zinc-500">Cerró</dt>
                                                <dd class="text-zinc-800 dark:text-zinc-200">
                                                    {{ $nc->closedBy ? $nc->closedBy->name . ' · ' . $nc->closed_at?->format('d/m/Y') : '—' }}
                                                </dd></div>
                                        </dl>
                                    </div>
                                    <div>
                                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Observaciones</div>
                                        <p class="whitespace-pre-line text-xs text-zinc-700 dark:text-zinc-300">{{ $nc->observations ?: '—' }}</p>
                                    </div>
                                    <div>
                                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Lecciones aprendidas</div>
                                        <p class="whitespace-pre-line text-xs text-zinc-700 dark:text-zinc-300">{{ $nc->lessons_learned ?: '—' }}</p>
                                    </div>
                                </div>

                                <div class="mt-5 flex justify-end">
                                    <flux:button size="sm" icon="arrow-top-right-on-square" :href="route('no-conformidad.show', $nc)" wire:navigate>
                                        Abrir NC completa
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-zinc-500">
                                No se encontraron no conformidades.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $ncs->links() }}
    </div>
</div>