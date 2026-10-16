<?php

namespace App\Notifications;

use App\Notifications\Concerns\FormatsVerdenMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Resumen diario: los mismos grupos que el usuario ve en "Mis pendientes".
 *
 * @see \App\Support\NcPendingTasks::forDigest()
 */
class NcDailyDigest extends Notification implements ShouldQueue
{
    use Queueable, FormatsVerdenMail;

    /**
     * @param list<array{title: string, items: list<array{folio: string, department: string, detail: string, due: ?string, note: ?string, url: string}>}> $groups
     */
    public function __construct(public array $groups)
    {
    }

    public function total(): int
    {
        return array_sum(array_map(fn (array $g) => count($g['items']), $this->groups));
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = $this->total();

        $mail = (new MailMessage)
            ->subject("Resumen del día – {$total} " . ($total === 1 ? 'pendiente' : 'pendientes'))
            ->greeting('Hola, ' . Str::before($notifiable->name, ' ') . ':')
            ->line(new HtmlString('## Tus pendientes de hoy'))
            ->line('Estos son tus pendientes de No Conformidad en el sistema ' . config('app.name') . ':');

        foreach ($this->groups as $group) {
            $mail->line('**' . $group['title'] . ' (' . count($group['items']) . ')**');

            foreach ($group['items'] as $item) {
                $line = "• [{$item['folio']}]({$item['url']}) ({$item['department']}) — {$item['detail']}";

                if ($item['due']) {
                    $line .= " — {$item['due']}";
                }

                if ($item['note']) {
                    $line .= " — **{$item['note']}**";
                }

                $mail->line($line);
            }
        }

        return $mail
            ->line($this->centered('Para atenderlos, haz clic en el siguiente botón:'))
            ->action('Ver mis pendientes', route('pendientes.index'))
            ->salutation($this->signature());
    }

    public function toArray(object $notifiable): array
    {
        $parts = array_map(
            fn (array $g) => $g['title'] . ': ' . count($g['items']),
            $this->groups,
        );

        return [
            'event'      => 'resumen_diario',
            'title'      => 'Resumen del día',
            'detail'     => implode(' · ', $parts),
            'folio'      => null,
            'department' => null,
            'url'        => route('pendientes.index', [], false),
        ];
    }
}