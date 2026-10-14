@props(['attachment'])

@php
    $name = $attachment->original_name;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
    $isPdf = $ext === 'pdf';
    $isSheet = in_array($ext, ['xls', 'xlsx', 'xlsm', 'csv']);
    $isSlides = in_array($ext, ['ppt', 'pptx']);

    $download = route('no-conformidad.attachments.download', $attachment);
    $preview = route('no-conformidad.attachments.preview', $attachment);

    $tone = match (true) {
        $isPdf => 'text-red-600 bg-red-50 dark:text-red-400 dark:bg-red-500/10',
        $isSheet => 'text-green-700 bg-green-50 dark:text-green-400 dark:bg-green-500/10',
        $isSlides => 'text-orange-600 bg-orange-50 dark:text-orange-400 dark:bg-orange-500/10',
        in_array($ext, ['doc', 'docx']) => 'text-sky-600 bg-sky-50 dark:text-sky-400 dark:bg-sky-500/10',
        default => 'text-zinc-500 bg-zinc-50 dark:text-zinc-400 dark:bg-zinc-800',
    };

    $meta = $attachment->created_at->format('d/m/Y').' · '.$attachment->humanSize();
    $tooltip = $name.' — '.($attachment->uploader?->name ? $attachment->uploader->name.' · ' : '')
        .$attachment->created_at->format('d/m/Y H:i');
@endphp

<div class="w-20" title="{{ $tooltip }}" @if ($isImage) x-data="{ open: false }" @endif>
    @if ($isImage)
        <button type="button" x-on:click="open = true"
            class="group block size-20 overflow-hidden rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
            <img src="{{ $preview }}" alt="{{ $name }}" loading="lazy"
                class="size-full object-cover transition duration-200 group-hover:scale-105">
        </button>
    @else
        <a href="{{ $isPdf ? $preview : $download }}" @if ($isPdf) target="_blank" rel="noopener" @endif
            class="flex size-20 flex-col items-center justify-center gap-1 rounded-md border border-zinc-200 transition hover:border-accent dark:border-zinc-700 {{ $tone }}">
            @if ($isSheet)
                <flux:icon.table-cells class="size-6" />
            @elseif ($isSlides)
                <flux:icon.presentation-chart-bar class="size-6" />
            @else
                <flux:icon.document-text class="size-6" />
            @endif
            <span class="text-[10px] font-semibold uppercase tracking-wide">{{ $ext ?: 'archivo' }}</span>
        </a>
    @endif

    <div class="mt-1 truncate text-[11px] font-medium text-zinc-700 dark:text-zinc-300">{{ $name }}</div>
    <div class="truncate text-[10px] text-zinc-500">{{ $meta }}</div>

    {{-- Visor de imagen --}}
    @if ($isImage)
        <template x-teleport="body">
            <div x-show="open" style="display: none" x-transition.opacity
                x-on:keydown.escape.window="open = false" x-on:click.self="open = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
                <div class="flex max-w-4xl flex-col items-center">
                    <img src="{{ $preview }}" alt="{{ $name }}" class="max-h-[78vh] max-w-full rounded-md shadow-xl">
                    <div class="mt-3 flex w-full items-center justify-between gap-3">
                        <div class="min-w-0 text-sm text-white">
                            <div class="truncate font-medium">{{ $name }}</div>
                            <div class="text-xs text-zinc-300">{{ $tooltip }}</div>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <flux:button size="sm" icon="arrow-down-tray" :href="$download">Descargar</flux:button>
                            <flux:button size="sm" variant="ghost" icon="x-mark" x-on:click="open = false"
                                class="!text-white" />
                        </div>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>