@props(['number', 'title', 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700']) }}>
    <div class="flex items-stretch bg-[#92D050] text-sm font-semibold text-zinc-900">
        <div class="w-24 shrink-0 border-r border-zinc-900/30 px-3 py-1.5">Paso {{ $number }}:</div>
        <div class="flex-1 px-3 py-1.5 text-center">{{ $title }}</div>
    </div>

    @if ($subtitle)
        <div class="border-b border-zinc-200 px-3 py-1 text-center text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
            {{ $subtitle }}
        </div>
    @endif

    <div class="p-4">
        {{ $slot }}
    </div>
</section>