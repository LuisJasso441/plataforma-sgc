<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Usuarios')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(User $user): void
    {
        // Evita que un admin se desactive a sí mismo y se quede fuera
        if ($user->id === auth()->id()) {
            session()->flash('error', 'No puedes desactivar tu propia cuenta.');
            return;
        }

        $user->update(['active' => ! $user->active]);

        session()->flash('status', $user->active
            ? "Usuario \"{$user->name}\" activado."
            : "Usuario \"{$user->name}\" desactivado.");
    }

    public function with(): array
    {
        $users = User::query()
            ->with('department')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('username', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10);

        return [
            'users' => $users,
        ];
    }
};