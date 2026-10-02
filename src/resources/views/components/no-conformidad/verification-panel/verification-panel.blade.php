<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5">
    <flux:heading size="lg">Verificación de efectividad</flux:heading>
    <flux:subheading class="mb-4">¿Las acciones eliminaron el problema y evitan su reincidencia?</flux:subheading>

    @if ($nc->stage === App\Enums\NcStage::EnVerificacion)
        {{-- Pendiente --}}
        @if (! $isDue)
            <flux:callout icon="clock"
                heading="Verificación programada para el {{ $nc->verification_date->format('d/m/Y') }} (un mes después de la última fecha compromiso)." />
        @elseif (! $canVerify)
            <flux:callout icon="clock" heading="Pendiente de verificación por Calidad." />
        @endif

        {{-- Formulario (Calidad, a partir de la fecha) --}}
        @if ($canVerify)
            <form wire:submit="close" class="flex flex-col gap-5">
                <flux:radio.group wire:model.live="effective" label="¿Se cumplió? (acciones efectivas)">
                    <flux:radio value="si" label="Sí — las actividades son efectivas, el problema se eliminó" />
                    <flux:radio value="no" label="No — las actividades no son efectivas, el problema persiste" />
                </flux:radio.group>

                @if ($effective === 'no')
                    <flux:callout variant="warning" icon="exclamation-triangle"
                        heading="La NC se marcará como No efectiva y se abrirá automáticamente una nueva NC con el mismo líder, en etapa Pendiente de reporte." />
                @endif

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
        @endif
    @else
        {{-- Resultado (Cerrada / No efectiva) --}}
        <div class="flex flex-col gap-4 text-sm">
            <div class="flex flex-wrap items-center gap-2">
                @if ($nc->is_effective)
                    <flux:badge color="green" icon="check-circle">Actividades efectivas, eliminan el problema</flux:badge>
                @else
                    <flux:badge color="red" icon="x-circle">Actividades no efectivas, el problema persiste</flux:badge>
                @endif
                <span class="text-xs text-zinc-500">
                    {{ $nc->closedBy?->name }} · {{ $nc->closed_at?->format('d/m/Y H:i') }}
                </span>
            </div>

            @if ($reopenings->isNotEmpty())
                <div>
                    <span class="text-zinc-500">Folio apertura de nueva acción:</span>
                    @foreach ($reopenings as $reopening)
                        <a href="{{ route('no-conformidad.show', $reopening) }}" wire:navigate
                            class="font-medium text-sky-600 dark:text-sky-400 hover:underline">{{ $reopening->folio }}</a>
                        <span class="text-xs text-zinc-500">({{ $reopening->created_at->format('d/m/Y') }})</span>
                    @endforeach
                </div>
            @endif

            @if ($nc->observations)
                <div>
                    <div class="text-xs font-medium text-zinc-500">Observaciones</div>
                    <div class="whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $nc->observations }}</div>
                </div>
            @endif

            <div>
                <div class="text-xs font-medium text-zinc-500">Lección aprendida</div>
                <div class="whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $nc->lessons_learned }}</div>
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