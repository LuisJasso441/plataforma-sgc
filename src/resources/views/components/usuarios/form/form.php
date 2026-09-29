<?php

use App\Models\Department;
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

    public function mount(?User $user = null): void
    {
        if ($user && $user->exists) {
            $this->user = $user;
            $this->name = $user->name;
            $this->username = $user->username;
            $this->email = $user->email;
            $this->role = $user->role;
            $this->department_id = $user->department_id;
            $this->active = $user->active;
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

        if ($this->user) {
            $this->user->update($data);
        } else {
            User::create($data);
        }

        session()->flash('status', $this->user ? 'Usuario actualizado.' : 'Usuario creado.');

        $this->redirect(route('usuarios.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'departments' => Department::where('active', true)->orderBy('name')->get(),
        ];
    }
};