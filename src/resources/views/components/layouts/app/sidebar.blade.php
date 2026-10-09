@php
    $user = auth()->user();
    $userSubtitle = $user->roleLabel() . ($user->department ? ' · ' . $user->department->name : '');
    $unreadNotifications = $user->unreadNotifications()->count();

    // Módulos activos del SGA (cambian muy poco: caché de 10 minutos)
    $sidebarModules = \Illuminate\Support\Facades\Cache::remember('sidebar.modules', 600,
        fn () => \App\Models\Module::where('active', true)->orderBy('order')->get());
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-verden-ink">
        {{-- Barra lateral siempre verde bosque ("dark" aplica la variante oscura a su contenido) --}}
        <flux:sidebar sticky stashable class="dark border-r border-verden-forest bg-verden-forest">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2 text-white" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Plataforma" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        Inicio
                    </flux:navlist.item>

                    <flux:navlist.item icon="bell" :href="route('notificaciones.index')" :current="request()->routeIs('notificaciones.*')"
                        :badge="$unreadNotifications ?: null" wire:navigate>
                        Notificaciones
                    </flux:navlist.item>

                    {{-- Módulos del SGA: se muestran solo si el usuario tiene acceso y la ruta existe --}}
                    @foreach ($sidebarModules as $module)
                        @if ($user->canRead($module->key) && \Illuminate\Support\Facades\Route::has($module->key . '.index'))
                            <flux:navlist.item
                                icon="clipboard-document-list"
                                :href="route($module->key . '.index')"
                                :current="request()->routeIs($module->key . '.*')"
                                wire:navigate>
                                {{ $module->name }}
                            </flux:navlist.item>
                        @endif
                    @endforeach
                </flux:navlist.group>

                @if ($user->isAdmin())
                    <flux:navlist.group heading="Administración" class="grid">
                        <flux:navlist.item icon="users" :href="route('usuarios.index')" :current="request()->routeIs('usuarios.*')" wire:navigate>
                            Usuarios
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endif
            </flux:navlist>

            <flux:spacer />

            {{-- Menú del usuario (escritorio) --}}
            <flux:dropdown position="bottom" align="start">
                <flux:profile :name="$user->name" :initials="$user->initials()" icon-trailing="chevrons-up-down" />

                <flux:menu class="w-[240px]">
                    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground font-medium">
                            {{ $user->initials() }}
                        </span>
                        <div class="grid flex-1 leading-tight">
                            <span class="truncate font-semibold text-white">{{ $user->name }}</span>
                            <span class="truncate text-xs text-zinc-400">{{ $userSubtitle }}</span>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('settings.appearance')" icon="cog-6-tooth" wire:navigate>Ajustes</flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Cerrar sesión
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        {{-- Encabezado móvil --}}
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" class="ml-2 flex items-center gap-2" wire:navigate>
                <span class="flex size-7 items-center justify-center rounded-md bg-accent text-accent-foreground">
                    <x-app-logo-icon class="size-4" />
                </span>
                <span class="text-sm font-semibold">{{ config('app.name', 'VerdenSGA') }}</span>
            </a>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />

                <flux:menu>
                    <div class="px-1 py-1.5 text-left text-sm leading-tight">
                        <div class="truncate font-semibold">{{ $user->name }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ $userSubtitle }}</div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('settings.appearance')" icon="cog-6-tooth" wire:navigate>Ajustes</flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Cerrar sesión
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>