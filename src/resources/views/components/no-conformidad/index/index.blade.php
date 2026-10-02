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
        @can('create', App\Models\NonConformity::class)
            <flux:button variant="primary" icon="plus" :href="route('no-conformidad.create')" wire:navigate>
                Nueva NC
            </flux:button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-5">
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

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm text-left">
            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                <tr>
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
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 align-top">
                        <td class="px-4 py-3">
                            <a href="{{ route('no-conformidad.show', $nc) }}" wire:navigate
                                class="font-medium text-sky-600 dark:text-sky-400 whitespace-nowrap hover:underline">
                                {{ $nc->folio }}
                            </a>
                            <div class="text-zinc-500 text-xs whitespace-nowrap">{{ $nc->stage->label() }}</div>
                        </td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                            <div>{{ $nc->process->name }}</div>
                            <div class="text-zinc-500 text-xs">{{ $nc->subprocess?->name ?? 'N. A.' }}</div>
                        </td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                            <div>{{ $nc->department->name }}</div>
                            <div class="text-zinc-500 text-xs">{{ $nc->leader?->name ?? 'Sin líder asignado' }}</div>
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
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-zinc-500">
                            No se encontraron no conformidades.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $ncs->links() }}
    </div>
</div>