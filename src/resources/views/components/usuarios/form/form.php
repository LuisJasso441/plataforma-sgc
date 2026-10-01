<?php

use App\Models\Department;
use App\Models\Module;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Usuario')] class extends Component
{
    public ?User $user = null;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $role = 'user';
    public ?int $department_id = null;
    public bool $active = true;
    public string $password = '';
    public string $password_confirmation = '';

    // Matriz de permisos: [module_id => ['can_read'=>bool, 'can_create'=>bool, 'can_edit'=>bool]]
    public array $permissions = [];

    public function mount(?User $user = null): void
    {
        // Arranca la matriz con todos los módulos en falso
        foreach (Module::where('active', true)->get() as $module) {
            $this->permissions[$module->id] = [
                'can_read' => false,
                'can_create' => false,
                'can_edit' => false,
            ];
        }

        if ($user && $user->exists) {
            $this->user = $user;
            $this->name = $user->name;
            $this->username = $user->username;
            $this->email = $user->email;
            $this->role = $user->role;
            $this->department_id = $user->department_id;
            $this->active = $user->active;

            // Sobreescribe con los permisos guardados del usuario
            foreach ($user->modules as $module) {
                $this->permissions[$module->id] = [
                    'can_read' => (bool) $module->pivot->can_read,
                    'can_create' => (bool) $module->pivot->can_create,
                    'can_edit' => (bool) $module->pivot->can_edit,
                ];
            }
        }
    }

    protected function rules(): array
    {
        $userId = $this->user?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:50',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role' => ['required', 'in:admin,calidad,user'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'active' => ['boolean'],
            'password' => [
                $this->user ? 'nullable' : 'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'username.regex' => 'El usuario solo puede contener letras minúsculas, números, puntos, guiones y guiones bajos.',
        ];
    }

    public function updatedUsername(): void
    {
        // Normaliza a minúsculas mientras escribe
        $this->username = strtolower($this->username);
    }

    /**
     * Regla: Creador o Editor implican Lector.
     * Se dispara cuando cambia cualquier checkbox de la matriz.
     */
    public function updatedPermissions(): void
    {
        foreach ($this->permissions as $moduleId => $perms) {
            if (($perms['can_create'] ?? false) || ($perms['can_edit'] ?? false)) {
                $this->permissions[$moduleId]['can_read'] = true;
            }
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        // La contraseña solo se toca si se escribió algo
        if (! empty($this->password)) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($this->password);
        } else {
            unset($data['password']);
        }
        unset($data['password_confirmation']);

        $esNuevo = ! $this->user;

        if ($this->user) {
            $this->user->update($data);
        } else {
            $this->user = User::create($data);
        }

        // Sincronizar la matriz de permisos (solo para roles no-admin)
        $this->syncPermissions();

        \App\Models\Department::syncHeadFor($this->user->fresh());

        session()->flash('status', $esNuevo ? 'Usuario creado.' : 'Usuario actualizado.');

        $this->redirect(route('usuarios.index'), navigate: true);
    }

    /**
     * Construye el arreglo para sync() de la relación module_user.
     * Los admin tienen acceso total por su rol: no se les guarda matriz.
     * "Sin acceso" = sin fila (no se incluye el módulo en el sync).
     */
    protected function syncPermissions(): void
    {
        if ($this->role === 'admin') {
            $this->user->modules()->sync([]); // limpia cualquier permiso previo
            return;
        }

        $sync = [];
        foreach ($this->permissions as $moduleId => $perms) {
            $read = (bool) ($perms['can_read'] ?? false);
            $create = (bool) ($perms['can_create'] ?? false);
            $edit = (bool) ($perms['can_edit'] ?? false);

            // Solo se guarda fila si tiene al menos un permiso
            if ($read || $create || $edit) {
                $sync[$moduleId] = [
                    'can_read' => $read || $create || $edit, // Lector mínimo garantizado
                    'can_create' => $create,
                    'can_edit' => $edit,
                ];
            }
        }

        $this->user->modules()->sync($sync);
    }

    public function with(): array
    {
        return [
            'departments' => Department::where('active', true)->orderBy('name')->get(),
            'modules' => Module::where('active', true)->orderBy('order')->get(),
        ];
    }
};