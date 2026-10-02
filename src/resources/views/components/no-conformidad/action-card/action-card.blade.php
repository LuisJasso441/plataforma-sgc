<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
    {{-- Encabezado --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Acción {{ $action->number }}</div>
        <flux:badge size="sm" :color="$action->status->color()">{{ $action->status->label() }}</flux:badge>
    </div>

    <div class="mt-2 text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ $action->activity }}</div>

    <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-zinc-500 sm:grid-cols-3">
        <div>Responsable: <span class="text-zinc-800 dark:text-zinc-200">{{ $action->responsible }}</span></div>
        <div>
            Compromiso:
            <span class="{{ $action->isOverdue() ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-zinc-800 dark:text-zinc-200' }}">
                {{ $action->commitment_date->format('d/m/Y') }}{{ $action->isOverdue() ? ' (vencida)' : '' }}
            </span>
        </div>
        <div>Fecha real: <span class="text-zinc-800 dark:text-zinc-200">{{ $action->actual_end_date?->format('d/m/Y') ?? '—' }}</span></div>
    </div>

    {{-- Resultado de la revisión --}}
    @if ($action->status === App\Enums\NcActionStatus::Rechazada && $action->review_comment)
        <flux:callout variant="warning" icon="arrow-uturn-left" class="mt-3"
            heading="Calidad rechazó la evidencia" :text="$action->review_comment" />
    @endif

    @if ($action->status === App\Enums\NcActionStatus::Validada && $action->reviewer)
        <div class="mt-3 text-xs text-green-600 dark:text-green-400">
            Validada por {{ $action->reviewer->name }} · {{ $action->reviewed_at->format('d/m/Y H:i') }}
        </div>
    @endif

    @if ($action->status === App\Enums\NcActionStatus::EnRevision && ! $canReview)
        <div class="mt-3 text-xs text-sky-600 dark:text-sky-400">Evidencia en revisión por Calidad.</div>
    @endif

    {{-- Evidencias (todas las versiones) --}}
    @if ($evidences->isNotEmpty())
        <div class="mt-3">
            <div class="mb-1 text-xs font-medium text-zinc-500">Evidencias</div>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($evidences as $evidence)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                        <div class="flex min-w-0 items-center gap-2">
                            <flux:icon.paper-clip class="size-4 shrink-0 text-zinc-400" />
                            <a href="{{ route('no-conformidad.attachments.download', $evidence) }}"
                                class="truncate text-sm text-sky-600 dark:text-sky-400 hover:underline">
                                {{ $evidence->original_name }}
                            </a>
                        </div>
                        <div class="text-xs text-zinc-500">
                            {{ $evidence->uploader?->name }} · {{ $evidence->created_at->format('d/m/Y H:i') }} · {{ $evidence->humanSize() }}
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Subir evidencias (líder) --}}
    @if ($canUpload)
        <form wire:submit="submitEvidence" class="mt-4 flex flex-col gap-3">
            <flux:input type="file" wire:model="files" multiple
                accept=".jpg,.jpeg,.png,.webp,.pdf,.xlsx,.xls,.xlsm,.docx,.doc,.pptx"
                :label="$action->status === App\Enums\NcActionStatus::Rechazada ? 'Nuevas evidencias' : 'Evidencias'"
                description="Hasta 10 archivos de 20 MB: imágenes, PDF, Excel, Word o PowerPoint." />

            @foreach ($errors->get('files.*') as $messages)
                @foreach ($messages as $message)
                    <div class="text-xs text-red-500">{{ $message }}</div>
                @endforeach
            @endforeach

            <div wire:loading wire:target="files" class="text-xs text-zinc-500">Cargando archivos…</div>

            <div class="flex justify-end">
                <flux:button type="submit" size="sm" variant="primary" icon="paper-airplane"
                    wire:loading.attr="disabled" wire:target="files,submitEvidence">
                    Enviar evidencia a revisión
                </flux:button>
            </div>
        </form>
    @endif

    {{-- Revisión (Calidad) --}}
    @if ($canReview)
        <div class="mt-4 flex justify-end gap-2">
            <flux:modal.trigger name="rechazar-accion-{{ $action->id }}">
                <flux:button size="sm" icon="x-mark">Rechazar</flux:button>
            </flux:modal.trigger>
            <flux:button size="sm" variant="primary" icon="check" wire:click="validateAction"
                wire:confirm="¿Validar la acción {{ $action->number }}? Su fecha real de término será hoy.">
                Validar
            </flux:button>
        </div>

        <flux:modal name="rechazar-accion-{{ $action->id }}" class="md:w-[32rem]">
            <form wire:submit="rejectAction" class="space-y-6">
                <div>
                    <flux:heading size="lg">Rechazar evidencia · Acción {{ $action->number }}</flux:heading>
                    <flux:text class="mt-2">El líder verá el motivo y deberá subir nuevas evidencias.</flux:text>
                </div>

                <flux:textarea wire:model="rejectReason" label="Motivo" rows="4"
                    placeholder="Indica por qué la evidencia no cumple..." />

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">Rechazar</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>