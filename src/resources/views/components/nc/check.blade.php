@props(['checked' => false])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300']) }}>
    <span @class([
        'flex size-4 shrink-0 items-center justify-center rounded-sm border',
        'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' => $checked,
        'border-zinc-400 dark:border-zinc-500' => ! $checked,
    ])>
        @if ($checked)
            <flux:icon.check variant="micro" class="size-3" />
        @endif
    </span>
    {{ $slot }}
</span>