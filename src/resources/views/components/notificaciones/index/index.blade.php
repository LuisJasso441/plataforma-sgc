<div class="max-w-3xl">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Notificaciones</flux:heading>
            <flux:subheading>
                {{ $unread ? "{$unread} sin leer" : 'Estás al día' }}
            </flux:subheading>
        </div>

        @if ($unread)
            <flux:button size="sm" icon="check" wire:click="markAllAsRead">Marcar todas como leídas</flux:button>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            <button type="button" wire:click="open('{{ $notification->id }}')" wire:key="n-{{ $notification->id }}"
                @class([
                    'flex w-full items-start gap-3 border-b border-zinc-100 px-4 py-3 text-left last:border-b-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50',
                    'bg-accent/5' => ! $notification->read_at,
                ])>
                <span @class([
                    'mt-1.5 size-2 shrink-0 rounded-full',
                    'bg-accent' => ! $notification->read_at,
                    'bg-transparent' => $notification->read_at,
                ])></span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span @class(['text-sm text-zinc-900 dark:text-zinc-100', 'font-semibold' => ! $notification->read_at])>
                            {{ $data['title'] ?? 'Aviso' }}
                        </span>
                        @if (! empty($data['folio']))
                            <flux:badge size="sm" color="zinc">{{ $data['folio'] }} · {{ $data['department'] }}</flux:badge>
                        @endif
                    </div>
                    @if (! empty($data['detail']))
                        <div class="mt-0.5 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $data['detail'] }}</div>
                    @endif
                </div>

                <span class="shrink-0 text-xs text-zinc-500" title="{{ $notification->created_at->format('d/m/Y H:i') }}">
                    {{ $notification->created_at->locale('es')->diffForHumans() }}
                </span>
            </button>
        @empty
            <div class="px-4 py-10 text-center text-sm text-zinc-500">No tienes notificaciones.</div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
</div>