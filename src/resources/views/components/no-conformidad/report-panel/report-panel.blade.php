<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="lg">Reporte de No Conformidad</flux:heading>
            <flux:subheading>Formato llenado en la reunión multidisciplinaria</flux:subheading>
        </div>

        @can('downloadReport', $nc)
            <flux:button size="sm" icon="arrow-down-tray" :href="route('no-conformidad.report.download', $nc)">
                Descargar formato prellenado
            </flux:button>
        @endcan
    </div>

    @if ($nc->stage === App\Enums\NcStage::PendienteReporte)
        <flux:callout icon="information-circle" class="mb-4"
            heading="Descarga el formato prellenado, convoca la reunión multidisciplinaria, termina de llenarlo y súbelo aquí." />
    @endif

    @if ($lastReturn)
        <flux:callout variant="warning" icon="arrow-uturn-left" class="mb-4"
            heading="Calidad devolvió el reporte" :text="$lastReturn" />
    @endif

    @if ($nc->stage === App\Enums\NcStage::ReporteEnRevision && ! $canReview)
        <flux:callout icon="clock" class="mb-4" heading="Reporte en revisión por Calidad." />
    @endif

    {{-- Versiones del reporte --}}
    @if ($reports->isEmpty())
        <div class="text-sm text-zinc-500">Aún no se ha subido el reporte.</div>
    @else
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($reports as $report)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <flux:icon.document-text class="size-5 shrink-0 text-zinc-400" />
                        <a href="{{ route('no-conformidad.attachments.download', $report) }}"
                            class="truncate text-sm font-medium text-sky-600 dark:text-sky-400 hover:underline">
                            {{ $report->original_name }}
                        </a>
                        @if ($loop->first)
                            <flux:badge size="sm" color="green">Vigente</flux:badge>
                        @endif
                    </div>
                    <div class="text-xs text-zinc-500">
                        {{ $report->uploader?->name }} · {{ $report->created_at->format('d/m/Y H:i') }} · {{ $report->humanSize() }}
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Subir reporte (líder) --}}
    @if ($canUpload)
        <form wire:submit="sendReport" class="mt-4 flex flex-col gap-3">
            <flux:input type="file" wire:model="report" accept=".xlsx,.xls,.xlsm"
                :label="$reports->isEmpty() ? 'Archivo Excel del reporte' : 'Nueva versión del reporte'" />

            <div wire:loading wire:target="report" class="text-xs text-zinc-500">Cargando archivo…</div>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="arrow-up-tray"
                    wire:loading.attr="disabled" wire:target="report,sendReport">
                    Subir y enviar a Calidad
                </flux:button>
            </div>
        </form>
    @endif

    {{-- Revisión (Calidad) --}}
    @if ($canReview)
        <div class="mt-4 flex justify-end gap-2">
            <flux:modal.trigger name="devolver-reporte">
                <flux:button icon="arrow-uturn-left">Devolver reporte</flux:button>
            </flux:modal.trigger>
            <flux:button variant="primary" icon="check" wire:click="approve"
                wire:confirm="¿Aprobar el reporte? El líder pasará a capturar las acciones definitivas.">
                Aprobar reporte
            </flux:button>
        </div>

        <flux:modal name="devolver-reporte" class="md:w-[32rem]">
            <form wire:submit="returnReport" class="space-y-6">
                <div>
                    <flux:heading size="lg">Devolver reporte</flux:heading>
                    <flux:text class="mt-2">El líder verá este motivo y deberá subir una nueva versión.</flux:text>
                </div>

                <flux:textarea wire:model="returnReason" label="Motivo" rows="4"
                    placeholder="Indica qué debe corregirse en el reporte..." />

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">Devolver</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>