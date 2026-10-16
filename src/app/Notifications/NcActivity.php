<?php

namespace App\Notifications;

use App\Models\NcLog;
use App\Models\NonConformity;
use App\Notifications\Concerns\FormatsVerdenMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Aviso de un evento del flujo de una No Conformidad (correo + plataforma).
 * Todos los correos siguen la misma estructura; cambian asunto, título, frase, detalles y botón.
 */
class NcActivity extends Notification implements ShouldQueue
{
    use Queueable, FormatsVerdenMail;

    public function __construct(
        public NonConformity $nc,
        public NcLog $log,
        public string $role = '',
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
        $nc  = $this->nc->loadMissing(['department', 'issuer', 'leader', 'parent', 'reopenings', 'actions']);
        $tpl = $this->template($nc);

        $mail = (new MailMessage)
            ->subject($tpl['subject'])
            ->greeting('Hola, ' . Str::before($notifiable->name, ' ') . ':')
            ->line(new HtmlString('## ' . e($tpl['title'])))
            ->line($tpl['text']);

        // Detalles del registro: Folio, datos del evento y Departamento
        $items = collect(['Folio' => $nc->folio] + $tpl['details'] + ['Departamento' => $nc->department?->name])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $label) => '* **' . e($label) . ':** ' . e($value))
            ->implode("\n");

        $mail->line(new HtmlString("**Detalles del registro:**\n\n" . $items));

        // Motivo / cambios / comentario capturado en el evento
        if ($tpl['comment'] && filled($this->log->comment)) {
            $quote = implode("\n> ", preg_split('/\R/u', e(Str::limit($this->log->comment, 600))));
            $mail->line(new HtmlString('**' . e($tpl['comment']) . ":**\n\n> " . $quote));
        }

        return $mail
            ->line($this->centered($tpl['cta']))
            ->action($tpl['button'], $tpl['url'])
            ->salutation($this->signature());
    }

    public function toArray(object $notifiable): array
    {
        $nc = $this->nc->loadMissing('department');

        return [
            'event'      => $this->log->event,
            'title'      => $this->log->eventLabel(),
            'detail'     => $this->log->comment ? Str::limit($this->log->comment, 200) : null,
            'folio'      => $nc->folio,
            'department' => $nc->department?->name,
            'url'        => route('no-conformidad.show', $nc, false),
        ];
    }

    /** Textos de cada evento (y variante por destinatario cuando aplica) */
    protected function template(NonConformity $nc): array
    {
        $by     = $this->log->user?->name;
        $folio  = $nc->folio;
        $show   = route('no-conformidad.show', $nc);
        $date   = fn ($d) => $d?->format('d/m/Y');
        $view   = 'Para consultar los detalles de esta No Conformidad, haz clic en el siguiente botón:';
        $key    = $this->log->event === 'lider_reasignado' ? "lider_reasignado.{$this->role}" : $this->log->event;

        $base = [
            'details' => [],
            'comment' => 'Comentario',
            'cta'     => $view,
            'button'  => 'Ver No Conformidad',
            'url'     => $show,
        ];

        $tpl = match ($key) {
            'creada' => [
                'subject' => "Nueva No Conformidad registrada – Folio {$folio}",
                'title'   => 'Nueva No Conformidad',
                'text'    => 'Se ha registrado una nueva No Conformidad en el sistema ' . config('app.name') . '.',
                'details' => ['Registrada por' => $nc->issuer?->name],
            ],
            'corregida' => [
                'subject' => "No Conformidad corregida – Folio {$folio}",
                'title'   => 'No Conformidad corregida',
                'text'    => 'El emisor corrigió la No Conformidad y la envió nuevamente a revisión.',
                'details' => ['Corregida por' => $by],
            ],
            'devuelta' => [
                'subject' => "No Conformidad devuelta para corrección – Folio {$folio}",
                'title'   => 'No Conformidad devuelta',
                'text'    => 'Calidad devolvió la No Conformidad que registraste para que la corrijas y la vuelvas a enviar.',
                'details' => ['Devuelta por' => $by],
                'comment' => 'Motivo',
                'cta'     => 'Para corregirla, haz clic en el siguiente botón:',
                'button'  => 'Corregir No Conformidad',
                'url'     => route('no-conformidad.edit', $nc),
            ],
            'aceptada' => [
                'subject' => "No Conformidad aceptada – Folio {$folio}",
                'title'   => 'No Conformidad aceptada',
                'text'    => 'Calidad aceptó la No Conformidad. El siguiente paso es convocar la reunión multidisciplinaria y subir el reporte.',
                'details' => ['Aceptada por' => $by, 'Líder' => $nc->leader?->name],
            ],
            'reporte_enviado' => [
                'subject' => "Reporte enviado a revisión – Folio {$folio}",
                'title'   => 'Reporte enviado a revisión',
                'text'    => 'El líder subió el reporte de No Conformidad y acción correctiva para su revisión.',
                'details' => ['Enviado por' => $by, 'Versión' => (string) $nc->reports()->count()],
                'cta'     => 'Para revisar el reporte, haz clic en el siguiente botón:',
                'button'  => 'Revisar reporte',
            ],
            'reporte_devuelto' => [
                'subject' => "Reporte devuelto para corrección – Folio {$folio}",
                'title'   => 'Reporte devuelto',
                'text'    => 'Calidad devolvió el reporte. Corrígelo y sube una nueva versión.',
                'details' => ['Devuelto por' => $by],
                'comment' => 'Motivo',
                'cta'     => 'Para consultar el reporte, haz clic en el siguiente botón:',
                'button'  => 'Ver reporte',
            ],
            'reporte_aprobado' => [
                'subject' => "Reporte aprobado – Folio {$folio}",
                'title'   => 'Reporte aprobado',
                'text'    => 'Calidad aprobó el reporte. A continuación se registrarán las acciones definitivas.',
                'details' => ['Aprobado por' => $by],
            ],
            'acciones_capturadas' => [
                'subject' => "Acciones definitivas registradas – Folio {$folio}",
                'title'   => 'Acciones definitivas registradas',
                'text'    => 'Calidad registró las acciones definitivas. Ya puedes subir las evidencias de cada acción.',
                'details' => [
                    'Acciones'                => (string) $nc->actions->count(),
                    'Fecha de implementación' => $date($nc->commitment_date),
                ],
                'cta'     => 'Para consultar las acciones y subir las evidencias, haz clic en el siguiente botón:',
                'button'  => 'Ver acciones',
            ],
            'acciones_modificadas' => [
                'subject' => "Acciones modificadas – Folio {$folio}",
                'title'   => 'Acciones modificadas',
                'text'    => 'Calidad modificó las acciones de la No Conformidad. Revisa los cambios y las nuevas fechas compromiso.',
                'details' => ['Modificadas por' => $by, 'Fecha de implementación' => $date($nc->commitment_date)],
                'comment' => 'Cambios',
                'cta'     => 'Para consultar las acciones, haz clic en el siguiente botón:',
                'button'  => 'Ver acciones',
            ],
            'evidencia_enviada' => [
                'subject' => "Evidencia enviada a revisión – Folio {$folio}",
                'title'   => 'Evidencia enviada a revisión',
                'text'    => 'El líder envió evidencias para su validación.',
                'details' => ['Enviada por' => $by],
                'comment' => 'Detalle',
                'cta'     => 'Para revisar la evidencia, haz clic en el siguiente botón:',
                'button'  => 'Revisar evidencia',
            ],
            'accion_rechazada' => [
                'subject' => "Evidencia rechazada – Folio {$folio}",
                'title'   => 'Evidencia rechazada',
                'text'    => 'Calidad rechazó la evidencia de una acción. Sube nuevas evidencias para continuar.',
                'details' => ['Rechazada por' => $by],
                'comment' => 'Motivo',
                'cta'     => 'Para subir nuevas evidencias, haz clic en el siguiente botón:',
                'button'  => 'Subir nuevas evidencias',
            ],
            'implementacion_completa' => [
                'subject' => "No Conformidad en verificación – Folio {$folio}",
                'title'   => 'Acciones validadas',
                'text'    => 'Se validaron todas las acciones.'
                    . ($nc->verification_date ? ' La efectividad se evaluará a partir del ' . $date($nc->verification_date) . '.' : ''),
                'details' => ['Fecha de verificación' => $date($nc->verification_date)],
            ],
            'verificada_efectiva' => [
                'subject' => "No Conformidad cerrada – Folio {$folio}",
                'title'   => 'No Conformidad cerrada',
                'text'    => 'Calidad evaluó las acciones como efectivas y cerró la No Conformidad.',
                'details' => ['Cerrada por' => $by, 'Fecha de cierre' => $date($nc->closed_at)],
            ],
            'verificada_no_efectiva' => (function () use ($nc, $folio, $by, $show) {
                $new = $nc->reopenings->sortByDesc('id')->first();

                return [
                    'subject' => "No Conformidad no efectiva – Folio {$folio}",
                    'title'   => 'Acciones no efectivas',
                    'text'    => 'Las acciones no fueron efectivas.'
                        . ($new ? " Se generó la No Conformidad {$new->folio} para darle seguimiento." : ''),
                    'details' => ['Nueva No Conformidad' => $new?->folio, 'Evaluada por' => $by],
                    'button'  => $new ? 'Ver nueva No Conformidad' : 'Ver No Conformidad',
                    'url'     => $new ? route('no-conformidad.show', $new) : $show,
                ];
            })(),
            'creada_por_no_efectiva' => [
                'subject' => "Nueva No Conformidad por acciones no efectivas – Folio {$folio}",
                'title'   => 'Nueva No Conformidad',
                'text'    => 'Se registró esta No Conformidad porque las acciones de la '
                    . ($nc->parent?->folio ?? 'No Conformidad anterior')
                    . ' no fueron efectivas. Quedaste como líder para darle seguimiento.',
                'details' => ['Viene de' => $nc->parent?->folio],
            ],
            'lider_reasignado.lider' => [
                'subject' => "Te asignaron como líder – Folio {$folio}",
                'title'   => 'Nueva asignación como líder',
                'text'    => 'Fuiste asignado como líder de esta No Conformidad.',
                'details' => ['Asignado por' => $by, 'Etapa actual' => $nc->stage->label()],
            ],
            'lider_reasignado.lider_anterior' => [
                'subject' => "Cambio de líder – Folio {$folio}",
                'title'   => 'Cambio de líder',
                'text'    => 'Ya no eres el líder de esta No Conformidad. El seguimiento queda a cargo de '
                    . ($nc->leader?->name ?? 'otro usuario') . '.',
                'details' => ['Nuevo líder' => $nc->leader?->name],
            ],
            default => [
                'subject' => $this->log->eventLabel() . " – Folio {$folio}",
                'title'   => $this->log->eventLabel(),
                'text'    => $this->log->eventLabel() . '.',
            ],
        };

        return array_merge($base, $tpl);
    }
}