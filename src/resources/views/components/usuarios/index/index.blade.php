<div>
    @if (session('status'))
        <flux:callout variant="success" class="mb-4" icon="check-circle" :heading="session('status')" />
    @endif
    @if (session('error'))
        <flux:callout variant="danger" class="mb-4" icon="exclamation-triangle" :heading="session('error')" />
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">Usuarios</flux:heading>
            <flux:subheading>Gestión de usuarios y accesos de la plataforma</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('usuarios.create')" wire:navigate>
            Nuevo usuario
        </flux:button>
    </div>

    <div class="mb-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre, usuario o correo..."
            icon="magnifying-glass"
        />
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm text-left">
            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                <tr>
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="px-4 py-3 font-medium">Usuario</th>
                    <th class="px-4 py-3 font-medium">Rol</th>
                    <th class="px-4 py-3 font-medium">Departamento</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($users as $user)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</div>
                            <div class="text-zinc-500 text-xs">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $user->username }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="match($user->role) {
                                'admin' => 'red',
                                'calidad' => 'blue',
                                default => 'zinc',
                            }">
                                {{ ucfirst($user->role) }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                            {{ $user->department?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->active)
                                <flux:badge size="sm" color="green">Activo</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Inactivo</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil-square"
                                    :href="route('usuarios.edit', $user)" wire:navigate>
                                    Editar
                                </flux:button>

                                @if ($user->active)
                                    <flux:button size="sm" variant="ghost" icon="user-minus"
                                        wire:click="toggleActive({{ $user->id }})"
                                        wire:confirm="¿Desactivar a {{ $user->name }}? No podrá iniciar sesión.">
                                        Desactivar
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="user-plus"
                                        wire:click="toggleActive({{ $user->id }})">
                                        Activar
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                            No se encontraron usuarios.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>