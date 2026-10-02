<div>
    @if (session('status'))
        <flux:callout variant="success" class="mb-4" icon="check-circle" :heading="session('status')" />
    @endif
    @if (session('error'))
        <flux:callout variant="danger" class="mb-4" icon="exclamation-triangle" :heading="session('error')" />
    @endif

    {{-- Encabezado --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
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
                    {{ $nc->stage === App\Enums\NcStage::DevueltaEmisor ? 'Corregir' : 'Editar' }}
                </flux:button>
            @endcan

            @can('review', $nc)
                @if ($nc->stage === App\Enums\NcStage::Solicitada)
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
        {{-- Columna principal --}}
        <div class="flex flex-col gap-6 lg:col-span-2">
            {{-- Datos generales --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5">
                <flux:heading size="lg" class="mb-4">Datos generales</flux:heading>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-zinc-500">Fecha de registro</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $nc->created_at->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Quién emite</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $nc->issuer->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Proceso donde se genera</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $nc->process->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Sub-proceso</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $nc->subprocess?->name ?? 'N. A.' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Departamento responsable</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $nc->department->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Líder de solución</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">
                            @if ($nc->leader)
                                {{ $nc->leader->name }}
                            @else
                                <span class="text-zinc-500">Se asigna al aceptar
                                    @if ($suggestedLeader) (sugerido: {{ $suggestedLeader->name }}) @endif
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Origen de acción</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $nc->origin->label() }}</dd>
                    </div>
                    @if ($nc->accepted_at)
                        <div>
                            <dt class="text-zinc-500">Aceptada por</dt>
                            <dd class="text-zinc-900 dark:text-zinc-100">
                                {{ $nc->acceptedBy?->name ?? '—' }} · {{ $nc->accepted_at->format('d/m/Y') }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Descripción --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5">
                <flux:heading size="lg" class="mb-4">Descripción de la No Conformidad</flux:heading>

                <div class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ $nc->initial_description }}</div>

                @if ($nc->description)
                    <flux:separator class="my-4" />
                    <div class="text-xs font-medium text-zinc-500 mb-1">Descripción actualizada (reunión)</div>
                    <div class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ $nc->description }}</div>
                @endif
            </div>
        </div>

        {{-- Columna lateral --}}
        <div class="flex flex-col gap-6">
            {{-- Fechas --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5">
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
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5">
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
                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400 whitespace-pre-line">{{ $log->comment }}</div>
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