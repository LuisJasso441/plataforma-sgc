<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[\Livewire\Attributes\Title('Ajustes')] class extends Component {
    //
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout heading="Apariencia" subheading="Elige cómo se ve la plataforma en este equipo">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">Claro</flux:radio>
            <flux:radio value="dark" icon="moon">Oscuro</flux:radio>
            <flux:radio value="system" icon="computer-desktop">Sistema</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</div>
