<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Notificaciones')] class extends Component
{
    use WithPagination;

    /** Marca como leída y lleva al destino del aviso */
    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('dashboard'), navigate: true);
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function with(): array
    {
        return [
            'notifications' => auth()->user()->notifications()->latest()->paginate(15),
            'unread'        => auth()->user()->unreadNotifications()->count(),
        ];
    }
};