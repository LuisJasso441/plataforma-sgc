@php
    $labelEffective   = 'Actividades efectivas, eliminan el problema';
    $labelIneffective = 'Actividades no efectivas, el problema persiste';
    $newAction        = $reopenings->first();
@endphp

<div class="flex flex-col gap-5">
    {{-- Aún no toca verificar --}}
    @if ($nc->stage === App\Enums\NcStage::EnVerificacion && ! $isDue)
        <flux:callout icon="clock"
            heading="Verificación programada para el {{ $nc->verification_date->format('d/m/Y') }} (un mes después de la última fecha compromiso)." />
    @elseif ($nc->stage === App\Enums\NcStage::EnVerificacion && ! $canVerify)
        <flux:callout icon="clock" heading="Pendiente de verificación por Calidad." />
    @endif

    {{-- ═════════ Formulario de verificación (Calidad) ═════════ --}}
    @if ($canVerify)
        <form wire:submit="close" class="flex flex-col gap-5">
            {{-- Tal como el formato --}}
            <div class="grid grid-cols-1 overflow-hidden rounded-lg border border-zinc-200 text-sm dark:border-zinc-700 md:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)_minmax(0,1.3fr)]">
                <div class="border-b border-zinc-200 p-3 dark:border-zinc-700 md:border-b-0 md:border-r">
                    <div class="text-xs font-semibold text-zinc-500">Fecha de revisión de efectividad (cierre de acción)</div>
                    <div class="mt-1 text-zinc-900 dark:text-zinc-100">{{ now()->format('d/m/Y') }}</div>
                    <div class="text-xs text-zinc-500">Se registra al guardar.</div>
                </div>

                <div class="border-b border-zinc-200 p-3 dark:border-zinc-700 md:border-b-0 md:border-r">
                    <flux:radio.group wire:model.live="effective">
                        <flux:radio value="si" :label="$labelEffective" />
                        <flux:radio value="no" :label="$labelIneffective" />
                    </flux:radio.group>
                    @error('effective') <div class="mt-1 text-xs text-red-500">{{ $message }}</div> @enderror
                </div>

                <div class="p-3">
                    <div class="text-xs font-semibold text-zinc-500">Folio apertura de nueva acción</div>
                    <div class="mt-1 text-zinc-500">{{ $effective === 'no' ? 'Se asigna automáticamente al guardar' : '—' }}</div>
                    <div class="mt-2 text-xs font-semibold text-zinc-500">Fecha apertura de nueva acción</div>
                    <div class="mt-1 text-zinc-500">{{ $effective === 'no' ? now()->format('d/m/Y') : '—' }}</div>
                </div>
            </div>

            @if ($effective === 'no')
                <flux:callout variant="warning" icon="exclamation-triangle"
                    heading="Se marcará como No efectiva y se abrirá automáticamente una nueva NC con el mismo líder, en etapa Pendiente de reporte." />
            @endif

            {{-- Datos de la bitácora (no forman parte del formato) --}}
            <div class="flex flex-col gap-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <div>
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Datos para la bitácora</div>
                    <div class="text-xs text-zinc-500">No forman parte del formato; se registran en la bitácora de No Conformidades.</div>
                </div>

                <flux:textarea wire:model="observations" label="Observaciones" rows="3"
                    :description="$effective === 'no' ? 'Obligatorio: explica por qué no fue efectiva.' : 'Opcional.'" />

                <flux:textarea wire:model="lessons_learned" label="Lección aprendida" rows="3" />

                <flux:checkbox.group wire:model="affectedProcesses" label="Procesos a los que afecta">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($processes as $process)
                            <flux:checkbox :value="$process->id" :label="$process->name" />
                        @endforeach
                    </div>
                </flux:checkbox.group>
            </div>

            <div class="flex justify-end">
                @if ($effective === 'no')
                    <flux:button type="submit" variant="danger" icon="x-circle"
                        wire:confirm="Se marcará como No efectiva y se abrirá una nueva NC. ¿Continuar?">
                        Marcar como no efectiva
                    </flux:button>
                @else
                    <flux:button type="submit" variant="primary" icon="check-circle"
                        wire:confirm="¿Cerrar la NC como efectiva?">
                        Cerrar NC
                    </flux:button>
                @endif
            </div>
        </form>

    {{-- ═════════ Resultado (Cerrada / No efectiva) ═════════ --}}
    @elseif ($nc->stage->isFinal())
        <div class="grid grid-cols-1 overflow-hidden rounded-lg border border-zinc-200 text-sm dark:border-zinc-700 md:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)_minmax(0,1.3fr)]">
            <div class="border-b border-zinc-200 p-3 dark:border-zinc-700 md:border-b-0 md:border-r">
                <div class="text-xs font-semibold text-zinc-500">Fecha de revisión de efectividad (cierre de acción)</div>
                <div class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $nc->closed_at?->format('d/m/Y') ?? '—' }}</div>
                <div class="text-xs text-zinc-500">{{ $nc->closedBy?->name }}</div>
            </div>

            <div class="flex flex-col gap-2 border-b border-zinc-200 p-3 dark:border-zinc-700 md:border-b-0 md:border-r">
                <x-nc.check :checked="$nc->is_effective === true">{{ $labelEffective }}</x-nc.check>
                <x-nc.check :checked="$nc->is_effective === false">{{ $labelIneffective }}</x-nc.check>
            </div>

            <div class="p-3">
                <div class="text-xs font-semibold text-zinc-500">Folio apertura de nueva acción</div>
                <div class="mt-1">
                    @if ($newAction)
                        <a href="{{ route('no-conformidad.show', $newAction) }}" wire:navigate
                            class="font-medium text-sky-600 hover:underline dark:text-sky-400">{{ $newAction->folio }}</a>
                    @else
                        <span class="text-zinc-500">—</span>
                    @endif
                </div>
                <div class="mt-2 text-xs font-semibold text-zinc-500">Fecha apertura de nueva acción</div>
                <div class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $newAction?->created_at->format('d/m/Y') ?? '—' }}</div>
            </div>
        </div>

        {{-- Datos de la bitácora --}}
        <div class="flex flex-col gap-4 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700">
            <div>
                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Datos para la bitácora</div>
                <div class="text-xs text-zinc-500">No forman parte del formato; se registran en la bitácora de No Conformidades.</div>
            </div>

            <div>
                <div class="text-xs font-medium text-zinc-500">Observaciones</div>
                <div class="whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $nc->observations ?: '—' }}</div>
            </div>

            <div>
                <div class="text-xs font-medium text-zinc-500">Lección aprendida</div>
                <div class="whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $nc->lessons_learned ?: '—' }}</div>
            </div>

            <div>
                <div class="mb-1 text-xs font-medium text-zinc-500">Procesos a los que afecta</div>
                @forelse ($affected as $process)
                    <flux:badge size="sm" color="zinc" class="mb-1">{{ $process->name }}</flux:badge>
                @empty
                    <span class="text-zinc-500">—</span>
                @endforelse
            </div>
        </div>
    @endif
</div>