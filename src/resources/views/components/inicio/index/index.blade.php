@php
    $card = 'rounded-xl border border-zinc-200 p-5 dark:border-zinc-700';
    $attentionTypes = [
        'vencida'      => ['label' => 'Acción vencida', 'color' => 'amber'],
        'compromiso'   => ['label' => 'Compromiso',     'color' => 'sky'],
        'verificacion' => ['label' => 'Verificación',   'color' => 'violet'],
    ];
@endphp

<div class="flex flex-col gap-6">
    {{-- ═════════ Encabezado ═════════ --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $today }}</div>
            <flux:heading size="xl" class="mt-1">{{ $greeting }}, {{ $firstName }}</flux:heading>
            <flux:subheading>
                @if ($mode === 'nc')
                    {{ $total }} {{ $total === 1 ? 'no conformidad' : 'no conformidades' }}
                    {{ $year ? "en {$year}" : 'en total' }}
                @elseif ($mode === 'soporte')
                    Administración técnica de {{ config('app.name', 'VerdenSGA') }}
                @else
                    Bienvenido a {{ config('app.name', 'VerdenSGA') }}
                @endif
            </flux:subheading>
        </div>

        @if ($mode === 'nc')
            <div class="flex items-center gap-2">
                <flux:select wire:model.live="year" size="sm" class="w-32">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($years as $y)
                        <flux:select.option :value="$y">{{ $y }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button size="sm" icon="clipboard-document-list" :href="route('no-conformidad.index')" wire:navigate>
                    Ver bitácora
                </flux:button>
            </div>
        @endif
    </div>

    {{-- ═════════ Soporte ═════════ --}}
    @if ($mode === 'soporte')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([
                ['Usuarios activos', $support['activos'], 'users'],
                ['Usuarios inactivos', $support['inactivos'], 'user-minus'],
                ['Departamentos', $support['departamentos'], 'building-office-2'],
            ] as [$label, $value, $icon])
                <div class="{{ $card }}">
                    <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <flux:icon :name="$icon" variant="mini" class="size-4" /> {{ $label }}
                    </div>
                    <div class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="{{ $card }}">
            <flux:heading size="lg">Accesos rápidos</flux:heading>
            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button icon="users" :href="route('usuarios.index')" wire:navigate>Usuarios</flux:button>
                <flux:button icon="user-plus" :href="route('usuarios.create')" wire:navigate>Nuevo usuario</flux:button>
                <flux:button icon="cog-6-tooth" :href="route('settings.appearance')" wire:navigate>Ajustes</flux:button>
            </div>
        </div>

    {{-- ═════════ Sin módulos ═════════ --}}
    @elseif ($mode === 'sin_acceso')
        <flux:callout icon="information-circle" class="max-w-2xl"
            heading="Aún no tienes módulos asignados."
            text="Solicita acceso al área de Sistemas para comenzar a usar la plataforma." />

    {{-- ═════════ No Conformidad ═════════ --}}
    @else
        {{-- Tarjetas por estatus --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
            @foreach ($tileOrder as $key)
                <div class="{{ $card }}">
                    <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $categories[$key]['color'] }}"></span>
                        {{ $categories[$key]['label'] }}
                    </div>
                    <div class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $counts[$key] }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Por departamento --}}
            <div class="{{ $card }} lg:col-span-2">
                <flux:heading size="lg">No conformidades por departamento</flux:heading>

                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                    @foreach ($categories as $cat)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2.5 rounded-sm" style="background: {{ $cat['color'] }}"></span>{{ $cat['label'] }}
                        </span>
                    @endforeach
                </div>

                <div class="mt-5 flex flex-col gap-4">
                    @forelse ($byDepartment as $row)
                        <div class="grid grid-cols-[8.5rem_minmax(0,1fr)_2rem] items-start gap-3">
                            <div class="pt-0.5">
                                <flux:badge size="sm" :color="$row['department']->badgeColor()">{{ $row['department']->name }}</flux:badge>
                            </div>

                            <div class="min-w-0">
                                <div class="flex h-3 gap-0.5" style="width: {{ $row['total'] / $maxTotal * 100 }}%">
                                    @foreach ($row['segments'] as $key => $n)
                                        <div class="h-full min-w-1 first:rounded-l last:rounded-r"
                                            style="flex: {{ $n }} 1 0%; background: {{ $categories[$key]['color'] }}"
                                            title="{{ $categories[$key]['label'] }}: {{ $n }}"></div>
                                    @endforeach
                                </div>
                                {{-- Etiquetas visibles: el color no es la única pista --}}
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $row['segments']->map(fn ($n, $key) => $categories[$key]['label'] . ' ' . $n)->implode(' · ') }}
                                </div>
                            </div>

                            <div class="text-right text-sm font-medium tabular-nums text-zinc-900 dark:text-white">{{ $row['total'] }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-zinc-500">Sin no conformidades en el periodo.</div>
                    @endforelse
                </div>
            </div>

            {{-- Indicadores --}}
            <div class="flex flex-col gap-6">
                <div class="{{ $card }}">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Efectividad</div>
                    @if ($effectiveness !== null)
                        <div class="mt-1 text-4xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $effectiveness }}%</div>
                        <div class="mt-3 flex h-2 gap-0.5">
                            @if ($counts['cerrada'])
                                <div class="h-full rounded-l last:rounded-r" style="flex: {{ $counts['cerrada'] }} 1 0%; background: {{ $categories['cerrada']['color'] }}"></div>
                            @endif
                            @if ($counts['no_efectiva'])
                                <div class="h-full first:rounded-l rounded-r" style="flex: {{ $counts['no_efectiva'] }} 1 0%; background: {{ $categories['no_efectiva']['color'] }}"></div>
                            @endif
                        </div>
                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $counts['cerrada'] }} de {{ $concluded }} NC concluidas fueron efectivas.
                        </div>
                    @else
                        <div class="mt-1 text-sm text-zinc-500">Aún no hay NC concluidas.</div>
                    @endif
                </div>

                <div class="{{ $card }}">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Tiempo promedio de cierre</div>
                    @if ($avgDays !== null)
                        <div class="mt-1 text-4xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                            {{ $avgDays }} <span class="text-lg font-normal text-zinc-500">días</span>
                        </div>
                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Del registro a la verificación de efectividad.</div>
                    @else
                        <div class="mt-1 text-sm text-zinc-500">Aún no hay NC concluidas.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Requiere atención --}}
        <div class="{{ $card }}">
            <flux:heading size="lg">Requiere atención</flux:heading>
            <flux:subheading>Acciones vencidas, compromisos y verificaciones de los próximos 7 días</flux:subheading>

            @if ($attention->isEmpty())
                <div class="mt-4 flex items-center gap-2 text-sm text-zinc-500">
                    <flux:icon.check-circle variant="mini" class="size-5 text-green-600" /> Todo al día.
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-zinc-500">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Tipo</th>
                                <th class="py-2 pr-4 font-medium">Folio</th>
                                <th class="py-2 pr-4 font-medium">Departamento</th>
                                <th class="py-2 pr-4 font-medium">Detalle</th>
                                <th class="py-2 text-right font-medium">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($attention as $item)
                                <tr class="align-top">
                                    <td class="py-2 pr-4">
                                        <flux:badge size="sm" :color="$attentionTypes[$item['type']]['color']">{{ $attentionTypes[$item['type']]['label'] }}</flux:badge>
                                    </td>
                                    <td class="py-2 pr-4 whitespace-nowrap">
                                        <a href="{{ route('no-conformidad.show', $item['nc']) }}" wire:navigate
                                            class="font-medium text-sky-600 hover:underline dark:text-sky-400">{{ $item['nc']->folio }}</a>
                                    </td>
                                    <td class="py-2 pr-4">
                                        <flux:badge size="sm" :color="$item['nc']->department->badgeColor()">{{ $item['nc']->department->name }}</flux:badge>
                                    </td>
                                    <td class="py-2 pr-4 text-zinc-700 dark:text-zinc-300">{{ $item['detail'] }}</td>
                                    <td class="py-2 text-right whitespace-nowrap tabular-nums {{ $item['date']->lt(today()) ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                        {{ $item['date']->isToday() ? 'Hoy' : $item['date']->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>