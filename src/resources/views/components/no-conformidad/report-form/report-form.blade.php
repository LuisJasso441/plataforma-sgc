<div>
    <div class="mb-6">
        <flux:heading size="xl">Datos del reporte · {{ $nc->folio }}</flux:heading>
        <flux:subheading>
            Pasos 3 a 7. La descripción y las acciones definitivas se editan en la captura de acciones.
            Si se sube una nueva versión del reporte, estos datos se reemplazan por los del archivo.
        </flux:subheading>
    </div>

    <form wire:submit="save" class="flex max-w-4xl flex-col gap-6">
        {{-- Paso 3 --}}
        <x-nc.step number="3" title="Equipo de trabajo" subtitle="El equipo debe ser multidisciplinario en la medida de lo posible">
            <div class="flex flex-col gap-3">
                @foreach ($equipo as $i => $member)
                    <div wire:key="equipo-{{ $i }}" class="grid grid-cols-1 items-start gap-3 sm:grid-cols-[1fr_1fr_auto]">
                        <flux:input wire:model="equipo.{{ $i }}.nombre" placeholder="Nombre" />
                        <flux:input wire:model="equipo.{{ $i }}.area" placeholder="Área" />
                        <flux:button variant="ghost" icon="trash" wire:click="removeRow('equipo', {{ $i }})" />
                    </div>
                @endforeach
                <div>
                    <flux:button size="sm" icon="plus" wire:click="addRow('equipo')">Agregar integrante</flux:button>
                </div>
            </div>
        </x-nc.step>

        {{-- Paso 4 --}}
        <x-nc.step number="4" title="Acciones de contención" subtitle="Defina las acciones inmediatas de contención (24 horas máximo)">
            <div class="flex flex-col gap-4">
                @foreach ($contencion as $i => $item)
                    <div wire:key="contencion-{{ $i }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Contención {{ $i + 1 }}</div>
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeRow('contencion', {{ $i }})">Quitar</flux:button>
                        </div>
                        <div class="flex flex-col gap-3">
                            <flux:textarea wire:model="contencion.{{ $i }}.actividad" rows="2" label="Actividad / evidencia de realización" />
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <flux:input wire:model="contencion.{{ $i }}.responsable" label="Responsable" />
                                <flux:input type="date" wire:model="contencion.{{ $i }}.fecha_inicio" label="Fecha inicio" />
                                <flux:input type="date" wire:model="contencion.{{ $i }}.fecha_final" label="Fecha final (si aplica)" />
                            </div>
                        </div>
                    </div>
                @endforeach
                <div>
                    <flux:button size="sm" icon="plus" wire:click="addRow('contencion')">Agregar acción de contención</flux:button>
                </div>
            </div>
        </x-nc.step>

        {{-- Paso 6 --}}
        <x-nc.step number="6" title="Resultado del análisis de causa raíz"
            subtitle="Describa el origen del problema o sus principales causas probables">
            <flux:textarea wire:model="causa_raiz" rows="4" label="Causa(s) raíz" />
        </x-nc.step>

        {{-- Paso 7 --}}
        <x-nc.step number="7" title="Acciones definitivas" subtitle="Revisar el impacto del problema en otros productos y procesos">
            <div class="flex flex-col gap-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-[14rem_1fr]">
                    <flux:select wire:model="similares_aplica" label="¿Aplica a procesos similares?">
                        <flux:select.option value="">Sin marcar</flux:select.option>
                        <flux:select.option value="si">SI</flux:select.option>
                        <flux:select.option value="no">NO</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="similares_cuales" label="¿Cuáles?" />
                </div>

                <div class="flex flex-col gap-4">
                    <div class="max-w-56">
                        <flux:select wire:model="cambios_opcion" label="¿Requiere cambios en documentos?">
                            <flux:select.option value="">Sin marcar</flux:select.option>
                            <flux:select.option value="si">SI</flux:select.option>
                            <flux:select.option value="no">NO</flux:select.option>
                            <flux:select.option value="creacion">CREACIÓN</flux:select.option>
                        </flux:select>
                    </div>

                    <flux:checkbox.group wire:model="cambios_documentos" label="Documentos">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-4">
                            @foreach ($documentCatalog as $doc)
                                <flux:checkbox :value="$doc" :label="$doc" />
                            @endforeach
                        </div>
                    </flux:checkbox.group>

                    <flux:input wire:model="cambios_otro" label="Otro documento" />
                </div>

                <flux:separator />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_14rem]">
                    <flux:textarea wire:model="efectividad_evidencia" rows="3"
                        label="Efectividad de acciones"
                        description="Cómo se demostrará (con qué evidencia) que el problema ha sido eliminado y no será recurrente." />
                    <flux:input wire:model="efectividad_plazo" label="Plazo / fecha para verificación" />
                </div>
            </div>
        </x-nc.step>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('no-conformidad.show', $nc)" wire:navigate variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">Guardar datos del reporte</flux:button>
        </div>
    </form>
</div>