<?php

namespace App\Notifications;

use App\Models\NonConformity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Aviso de un evento del flujo de una No Conformidad (correo + plataforma).
 */
class NcActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NonConformity $nc,
        public string $title,
        public ?string $detail = null,
        public string $event = '',
    ) {
        // Se envía solo cuando la transacción que lo originó ya se guardó
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nc = $this->nc->loadMissing(['department', 'process']);

        $mail = (new MailMessage)
            ->subject("{$nc->folio} · {$nc->department->name} · {$this->title}")
            ->greeting('Hola, ' . Str::before($notifiable->name, ' '))
            ->line("**{$this->title}**")
            ->line("No conformidad **{$nc->folio}** — {$nc->department->name} · {$nc->process->name}");

        if ($this->detail) {
            $mail->line(Str::limit($this->detail, 600));
        }

        return $mail
            ->action('Ver la no conformidad', route('no-conformidad.show', $nc))
            ->salutation(config('app.name') . ' · Sistema de Gestión Ambiental');
    }

    public function toArray(object $notifiable): array
    {
        $nc = $this->nc->loadMissing('department');

        return [
            'event'      => $this->event,
            'title'      => $this->title,
            'detail'     => $this->detail ? Str::limit($this->detail, 200) : null,
            'folio'      => $nc->folio,
            'department' => $nc->department?->name,
            'url'        => route('no-conformidad.show', $nc, false),
        ];
    }
}