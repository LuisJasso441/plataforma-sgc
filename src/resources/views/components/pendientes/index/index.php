<?php

use App\Support\NcPendingTasks;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Mis pendientes')] class extends Component
{
    public function with(): array
    {
        $groups = NcPendingTasks::for(auth()->user());

        return [
            'groups' => $groups,
            'total'  => $groups->sum(fn (array $g) => $g['items']->count()),
        ];
    }
};