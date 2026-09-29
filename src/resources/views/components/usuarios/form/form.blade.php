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
            <flux:select wire:model="role" label="Rol">
                <flux:select.option value="user">Usuario</flux:select.option>
                <flux:select.option value="calidad">Calidad</flux:select.option>
                <flux:select.option value="admin">Administrador (Sistemas)</flux:select.option>
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

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('usuarios.index')" wire:navigate variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">{{ $user ? 'Guardar cambios' : 'Crear usuario' }}</flux:button>
        </div>
    </form>
</div>