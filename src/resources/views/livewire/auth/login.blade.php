<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string')]
    public string $username = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['username' => $this->username, 'password' => $this->password, 'active' => true], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Usuario o contraseña incorrectos, o la cuenta está desactivada.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => 'Demasiados intentos. Vuelve a intentarlo en ' . ceil($seconds / 60) . ' minuto(s).',
        ]);
    }

    protected function messages(): array
    {
        return [
            'username.required' => 'Escribe tu usuario.',
            'password.required' => 'Escribe tu contraseña.',
        ];
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->username).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-8">
    <div>
        <flux:heading size="xl">Iniciar sesión</flux:heading>
        <flux:subheading>Ingresa tu usuario y contraseña para continuar.</flux:subheading>
    </div>

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-6">
        <flux:input wire:model="username" label="Usuario" type="text" name="username"
            required autofocus autocomplete="username" placeholder="tu.usuario" />

        <flux:input wire:model="password" label="Contraseña" type="password" name="password"
            required autocomplete="current-password" placeholder="••••••••" viewable />

        <flux:checkbox wire:model="remember" label="Recordarme en este equipo" />

        <flux:button variant="primary" type="submit" class="w-full">Iniciar sesión</flux:button>
    </form>

    <flux:text class="text-xs">
        ¿No tienes acceso u olvidaste tu contraseña? Solicítalo al área de Sistemas.
    </flux:text>
</div>
