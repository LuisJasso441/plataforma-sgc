<div>
    <div class="mb-6">
        <flux:heading size="xl">
            {{ $nc ? "No Conformidad {$nc->folio}" : 'Nueva No Conformidad' }}
        </flux:heading>
        <flux:subheading>
            {{ $nc
                ? 'Corrige los datos de la solicitud'
                : 'Registra la solicitud. Calidad la revisará antes de convocar al líder de solución.' }}
        </flux:subheading>
    </div>

    @if ($returnReason)
        <flux:callout variant="warning" icon="arrow-uturn-left" class="mb-6 max-w-2xl"
            heading="Calidad devolvió esta solicitud" :text="$returnReason" />
    @endif

    <form wire:submit="save" class="flex flex-col gap-6 max-w-2xl">
        {{-- Datos automáticos --}}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <flux:input label="Folio" :value="$nc?->folio ?? 'Automático'" readonly />
            <flux:input label="Fecha" :value="($nc?->created_at ?? now())->format('d/m/Y')" readonly />
            <flux:input label="Quién emite" :value="$nc?->issuer->name ?? auth()->user()->name" readonly />
        </div>

        <flux:separator />

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <flux:select wire:model="nc_process_id" label="Proceso donde se genera" placeholder="Selecciona un proceso...">
                @foreach ($processes as $process)
                    <flux:select.option :value="$process->id">{{ $process->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="nc_subprocess_id" label="Sub-proceso (opcional)" placeholder="N. A.">
                <flux:select.option value="">N. A.</flux:select.option>
                @foreach ($subprocesses as $subprocess)
                    <flux:select.option :value="$subprocess->id">{{ $subprocess->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div>
            <flux:select wire:model.live="department_id" label="Departamento de la NC (responsable)" placeholder="Selecciona un área...">
                @foreach ($departments as $department)
                    <flux:select.option :value="$department->id">{{ $department->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($selectedDepartment)
                <div class="mt-2 text-xs text-zinc-500">
                    @if ($leader = $selectedDepartment->effectiveHead())
                        Líder de solución: <span class="font-medium">{{ $leader->name }}</span>
                        ({{ $selectedDepartment->head ? 'jefe del área' : 'jefe de ' . $selectedDepartment->parent->name }})
                    @else
                        <span class="text-amber-600 dark:text-amber-400">
                            Este departamento no tiene jefe asignado; Calidad deberá asignar el líder al aceptar la NC.
                        </span>
                    @endif
                </div>
            @endif
        </div>

        <flux:radio.group wire:model="origin" label="Origen de acción">
            @foreach ($origins as $o)
                <flux:radio :value="$o->value" :label="$o->label()" />
            @endforeach
        </flux:radio.group>

        <flux:textarea wire:model="initial_description" label="Descripción de la No Conformidad" rows="6"
            placeholder="Qué sucedió, cantidad, operación, fecha... (qué, quién, cómo, cuándo, dónde, cuánto)" />

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('no-conformidad.index')" wire:navigate variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">
                @if (! $nc)
                    Registrar y enviar a Calidad
                @elseif ($nc->stage === App\Enums\NcStage::DevueltaEmisor)
                    Corregir y reenviar
                @else
                    Guardar cambios
                @endif
            </flux:button>
        </div>
    </form>
</div>