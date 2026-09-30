<div>
    <div class="mb-6">
        <flux:heading size="xl">{{ $user ? 'Editar usuario' : 'Nuevo usuario' }}</flux:heading>
        <flux:subheading>
            {{ $user ? 'Modifica los datos y accesos del usuario' : 'Registra un nuevo usuario en la plataforma' }}
        </flux:subheading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 max-w-2xl">
        <flux:input wire:model="name" label="Nombre completo" type="text" required autofocus placeholder="Nombre y apellidos" />

        <flux:input wire:model="username" label="Usuario (para iniciar sesión)" type="text" required placeholder="ej. ljaramillo" />

        <flux:input wire:model="email" label="Correo electrónico" type="email" required placeholder="usuario@empresa.com" />

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <flux:select wire:model.live="role" label="Rol">
                <flux:select.option value="user">Jefe de Departamento</flux:select.option>
                <flux:select.option value="calidad">Calidad</flux:select.option>
                <flux:select.option value="admin">Soporte</flux:select.option>
            </flux:select>

            <flux:select wire:model="department_id" label="Departamento" placeholder="Selecciona un área...">
                @foreach ($departments as $department)
                    <flux:select.option :value="$department->id">{{ $department->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:separator />

        <div class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ $user
                ? 'Deja la contraseña en blanco para conservar la actual.'
                : 'Asigna una contraseña. El usuario la usará junto con su nombre de usuario.' }}
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <flux:input wire:model="password" label="Contraseña" type="password" autocomplete="new-password"
                :required="! $user" placeholder="Mín. 8, con mayúscula y número" viewable />

            <flux:input wire:model="password_confirmation" label="Confirmar contraseña" type="password"
                autocomplete="new-password" :required="! $user" placeholder="Repite la contraseña" viewable />
        </div>

        <flux:field variant="inline">
            <flux:switch wire:model="active" />
            <flux:label>Usuario activo</flux:label>
        </flux:field>

        <flux:separator />

        {{-- Matriz de permisos: solo para roles que no son admin --}}
        @if ($role === 'admin')
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 text-sm text-zinc-600 dark:text-zinc-400">
                El personal de Soporte tiene acceso completo a todos los módulos por su rol. No requiere asignación de permisos.
            </div>
        @else
            <div>
                <flux:heading size="lg">Permisos por módulo</flux:heading>
                <flux:subheading class="mb-3">
                    Marca los niveles de acceso. Al elegir Creador o Editor, Lector se activa automáticamente. Sin ninguna marca, el usuario no verá el módulo.
                </flux:subheading>

                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">Módulo</th>
                                <th class="px-4 py-3 font-medium text-center">Lector</th>
                                <th class="px-4 py-3 font-medium text-center">Creador</th>
                                <th class="px-4 py-3 font-medium text-center">Editor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($modules as $module)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $module->name }}</div>
                                        @if ($module->description)
                                            <div class="text-zinc-500 text-xs">{{ $module->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <flux:checkbox wire:model.live="permissions.{{ $module->id }}.can_read" />
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <flux:checkbox wire:model.live="permissions.{{ $module->id }}.can_create" />
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <flux:checkbox wire:model.live="permissions.{{ $module->id }}.can_edit" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('usuarios.index')" wire:navigate variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">{{ $user ? 'Guardar cambios' : 'Crear usuario' }}</flux:button>
        </div>
    </form>
</div>