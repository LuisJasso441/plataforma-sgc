<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-verden-ink">
        <div class="grid min-h-svh lg:grid-cols-[minmax(0,5fr)_minmax(0,4fr)]">

            {{-- ═════════ Panel de marca (escritorio) ═════════ --}}
            <aside class="dark relative hidden overflow-hidden bg-verden-forest p-10 text-white lg:flex lg:flex-col">
                {{-- Fondo: brillo menta y hoja de marca de agua --}}
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_10%,rgba(126,217,200,0.18),transparent_55%)]"></div>
                <x-app-logo-icon class="pointer-events-none absolute -bottom-24 -right-24 size-[30rem] text-white/[0.04]" />

                <div class="relative flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-verden-mint text-verden-forest">
                        <x-app-logo-icon class="size-6" />
                    </span>
                    <div class="leading-tight">
                        <div class="text-lg font-semibold">{{ config('app.name', 'VerdenSGA') }}</div>
                        <div class="text-xs text-verden-mint/80">Sistema de Gestión Ambiental</div>
                    </div>
                </div>

                <div class="relative mt-auto max-w-md">
                    <h1 class="text-3xl font-semibold leading-tight">
                        Gestión ambiental,<br>
                        <span class="text-verden-mint">con evidencia.</span>
                    </h1>
                    <p class="mt-4 text-sm text-white/70">
                        Registro, seguimiento y cierre de no conformidades y acciones correctivas,
                        con la trazabilidad que piden las auditorías.
                    </p>

                    <ul class="mt-8 space-y-3 text-sm text-white/80">
                        @foreach (['Flujo guiado de principio a fin', 'Bitácora lista para auditoría', 'Seguimiento por departamento'] as $item)
                            <li class="flex items-center gap-3">
                                <flux:icon.check-circle variant="mini" class="size-5 text-verden-mint" />
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="relative mt-12 flex items-end justify-between gap-6 border-t border-white/10 pt-6">
                    <x-company-logo class="h-12 w-40 text-white" />
                    <span class="text-xs text-white/50">© {{ now()->year }} {{ config('app.name', 'VerdenSGA') }}</span>
                </div>
            </aside>

            {{-- ═════════ Formulario ═════════ --}}
            <main class="flex flex-col items-center justify-center gap-8 p-6 md:p-10">
                {{-- Marca en móvil --}}
                <div class="flex items-center gap-3 lg:hidden">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                        <x-app-logo-icon class="size-6" />
                    </span>
                    <div class="leading-tight">
                        <div class="text-lg font-semibold text-zinc-900 dark:text-white">{{ config('app.name', 'VerdenSGA') }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Sistema de Gestión Ambiental</div>
                    </div>
                </div>

                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @fluxScripts
    </body>
</html>