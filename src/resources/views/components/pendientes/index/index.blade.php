<div class="mx-auto w-full max-w-5xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">Mis pendientes</flux:heading>
            <flux:subheading>Lo que te toca atender en las No Conformidades, lo vencido primero.</flux:subheading>
        </div>
        @if ($total)
            <flux:badge color="lime">{{ $total }} {{ $total === 1 ? 'pendiente' : 'pendientes' }}</flux:badge>
        @endif
    </div>

    @if ($groups->isEmpty())
        <div class="flex flex-col items-center rounded-lg border border-dashed border-zinc-300 px-6 py-14 text-center dark:border-zinc-700">
            <flux:icon.check-circle class="size-10 text-verden-green dark:text-verden-mint" />
            <flux:heading size="lg" class="mt-3">Sin pendientes</flux:heading>
            <flux:text class="mt-1">Todo al día. Aquí aparecerá lo que requiera tu atención.</flux:text>
        </div>
    @else
        {{-- Resumen por grupo --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($groups as $group)
                <a href="#{{ $group['key'] }}"
                    class="inline-flex items-center gap-2 rounded-full border border-zinc-200 px-3 py-1 text-sm text-zinc-700 transition hover:border-accent dark:border-zinc-700 dark:text-zinc-300">
                    <flux:icon :icon="$group['icon']" variant="micro" class="text-zinc-400" />
                    {{ $group['title'] }}
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ $group['items']->count() }}</span>
                </a>
            @endforeach
        </div>

        @foreach ($groups as $group)
            <section id="{{ $group['key'] }}" wire:key="grupo-{{ $group['key'] }}"
                class="scroll-mt-6 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-2 border-b border-zinc-200 bg-zinc-50 px-4 py-2.5 dark:border-zinc-700 dark:bg-white/5">
                    <flux:icon :icon="$group['icon']" variant="mini" class="text-verden-green dark:text-verden-mint" />
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $group['title'] }}</span>
                    <flux:badge size="sm">{{ $group['items']->count() }}</flux:badge>
                </div>

                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($group['items'] as $item)
                        @php
                            $nc = $item['nc'];
                            $days = (int) abs($item['since']->copy()->startOfDay()->diffInDays(today()));
                        @endphp
                        <li wire:key="{{ $group['key'] }}-{{ $nc->id }}">
                            <a href="{{ route('no-conformidad.show', $nc) }}" wire:navigate
                                class="group flex flex-col gap-2 border-l-2 border-transparent px-4 py-3 transition hover:border-accent hover:bg-verden-green/5 dark:hover:bg-verden-mint/5 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $nc->folio }}</span>
                                        @if ($nc->department)
                                            <flux:badge size="sm" :color="$nc->department->badgeColor()">{{ $nc->department->name }}</flux:badge>
                                        @endif
                                        @if ($item['overdue'])
                                            <flux:badge size="sm" color="amber" variant="solid" icon="exclamation-triangle">Vencida</flux:badge>
                                        @endif
                                    </div>
                                    <div class="mt-1 truncate text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ Str::limit($nc->description ?: $nc->initial_description, 120) }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-zinc-500">{{ $item['detail'] }}</div>
                                </div>

                                <div class="flex shrink-0 items-center gap-3 text-xs text-zinc-500 sm:text-right">
                                    <div class="space-y-0.5">
                                        @if ($item['due'])
                                            <div class="{{ $item['overdue'] ? 'font-medium text-amber-600 dark:text-amber-400' : '' }}">
                                                {{ $item['dueLabel'] }}: {{ $item['due']->format('d/m/Y') }}
                                            </div>
                                        @endif
                                        <div>{{ $days === 0 ? 'Desde hoy' : 'Esperando '.$days.' '.($days === 1 ? 'día' : 'días') }}</div>
                                    </div>
                                    <flux:icon.chevron-right class="size-4 text-zinc-400 transition group-hover:text-accent" />
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>