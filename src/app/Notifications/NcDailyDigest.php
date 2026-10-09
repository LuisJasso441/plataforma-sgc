<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Resumen diario de pendientes (acciones vencidas / próximas, verificaciones).
 *
 * @param array<string, list<array{folio:string, department:string, detail:string, date:string, url:string}>> $sections
 */
class NcDailyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public const SECTIONS = [
        'vencidas'       => 'Acciones vencidas',
        'proximas'       => 'Acciones que vencen en los próximos 3 días',
        'verificaciones' => 'Verificaciones de efectividad pendientes',
    ];

    public function __construct(public array $sections)
    {
    }

    public function total(): int
    {
        return array_sum(array_map('count', $this->sections));
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = $this->total();

        $mail = (new MailMessage)
            ->subject("Resumen del día · {$total} " . ($total === 1 ? 'pendiente' : 'pendientes'))
            ->greeting('Hola, ' . Str::before($notifiable->name, ' '))
            ->line('Estos son tus pendientes de No Conformidad para hoy:');

        foreach (self::SECTIONS as $key => $label) {
            if (empty($this->sections[$key])) {
                continue;
            }

            $mail->line("**{$label}**");
            foreach ($this->sections[$key] as $item) {
                $mail->line("• [{$item['folio']}]({$item['url']}) ({$item['department']}) — {$item['detail']} — {$item['date']}");
            }
        }

        return $mail
            ->action('Ir a ' . config('app.name'), route('dashboard'))
            ->salutation(config('app.name') . ' · Sistema de Gestión Ambiental');
    }

    public function toArray(object $notifiable): array
    {
        $parts = [];
        foreach (self::SECTIONS as $key => $label) {
            if ($n = count($this->sections[$key] ?? [])) {
                $parts[] = "{$label}: {$n}";
            }
        }

        return [
            'event'      => 'resumen_diario',
            'title'      => 'Resumen del día',
            'detail'     => implode(' · ', $parts),
            'folio'      => null,
            'department' => null,
            'url'        => route('dashboard', [], false),
        ];
    }
}