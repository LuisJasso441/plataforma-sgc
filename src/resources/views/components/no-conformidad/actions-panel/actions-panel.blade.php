<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5">
    <flux:heading size="lg">Acciones definitivas</flux:heading>
    <flux:subheading class="mb-4">Actividades que eliminarán el problema de raíz y evitarán la reincidencia</flux:subheading>

    @if ($editable)
        {{-- Captura (líder) --}}
        <div class="flex flex-col gap-6">
            <flux:textarea wire:model="description" label="Descripción actualizada de la No Conformidad" rows="4"
                description="Ajusta la descripción con lo acordado en la reunión." />

            @if ($nc->trigger_date)
                <div class="text-sm">
                    <div class="text-zinc-500">Fecha de disparo de acción (reunión)</div>
                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $nc->trigger_date->format('d/m/Y') }}</div>
                    <div class="text-xs text-zinc-500">Tomada del campo Fecha del reporte vigente.</div>
                </div>
            @else
                <flux:callout variant="warning" icon="exclamation-triangle"
                    heading="No se encontró la fecha de disparo. Debe leerse del reporte vigente." />
            @endif

            <div class="flex flex-col gap-4">
                @foreach ($actions as $i => $action)
                    <div wire:key="nc-action-{{ $i }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Acción {{ $i + 1 }}</div>
                            @if (count($actions) > 1)
                                <flux:button size="sm" variant="ghost" icon="trash"
                                    wire:click="removeAction({{ $i }})">Quitar</flux:button>
                            @endif
                        </div>

                        <div class="flex flex-col gap-3">
                            <flux:textarea wire:model="actions.{{ $i }}.activity" rows="2"
                                label="Actividad / evidencia de realización" />

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <flux:input wire:model="actions.{{ $i }}.responsible" label="Responsable" />
                                <flux:input type="date" wire:model.live="actions.{{ $i }}.commitment_date"
                                    label="Fecha compromiso de cierre" />
                            </div>
                        </div>
                    </div>
                @endforeach

                <div>
                    <flux:button size="sm" icon="plus" wire:click="addAction">Agregar acción</flux:button>
                </div>
            </div>

            {{-- Fechas calculadas --}}
            <div class="grid grid-cols-1 gap-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 p-4 text-sm sm:grid-cols-2">
                <div>
                    <div class="text-zinc-500">Implementación (última fecha compromiso)</div>
                    <div class="font-medium">{{ $previewCommitment?->format('d/m/Y') ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-zinc-500">Verificación de efectividad (+1 mes)</div>
                    <div class="font-medium">{{ $previewVerification?->format('d/m/Y') ?? '—' }}</div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="saveDraft" icon="document-check">Guardar borrador</flux:button>
                <flux:button variant="primary" icon="paper-airplane" wire:click="submit"
                    wire:confirm="Al enviar a implementación las acciones quedan fijas y empieza el registro de evidencias. ¿Continuar?">
                    Enviar a implementación
                </flux:button>
            </div>
        </div>
    @else
        {{-- Solo lectura --}}
        @if ($nc->stage === App\Enums\NcStage::CapturaAcciones)
            <flux:callout icon="clock" class="mb-4" heading="El líder de solución está capturando las acciones definitivas." />
        @endif

        @if ($savedActions->isEmpty())
            <div class="text-sm text-zinc-500">Sin acciones registradas.</div>
        @elseif ($nc->stage->reached(App\Enums\NcStage::EnImplementacion))
            <div class="flex flex-col gap-4">
                @foreach ($savedActions as $action)
                    <livewire:no-conformidad.action-card :action="$action" :key="'action-card-'.$action->id" />
                @endforeach
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium min-w-64">Actividad</th>
                            <th class="px-4 py-3 font-medium">Responsable</th>
                            <th class="px-4 py-3 font-medium">Compromiso</th>
                            <th class="px-4 py-3 font-medium">Fecha real</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($savedActions as $action)
                            <tr class="align-top">
                                <td class="px-4 py-3">{{ $action->number }}</td>
                                <td class="px-4 py-3 whitespace-pre-line">{{ $action->activity }}</td>
                                <td class="px-4 py-3">{{ $action->responsible }}</td>
                                <td class="px-4 py-3 whitespace-nowrap {{ $action->isOverdue() ? 'text-amber-600 dark:text-amber-400 font-medium' : '' }}">
                                    {{ $action->commitment_date->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $action->actual_end_date?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" :color="$action->status->color()">{{ $action->status->label() }}</flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>