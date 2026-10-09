<?php

namespace App\Support;

use App\Models\NcLog;
use App\Models\NonConformity;
use App\Models\User;
use App\Notifications\NcActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Envía avisos a partir de los eventos de la línea de tiempo de la NC.
 * Los vencimientos y verificaciones van en el resumen diario (nc:daily-digest).
 */
class NcNotifier
{
    /** evento de nc_logs => destinatarios (calidad | lider | emisor) */
    public const RECIPIENTS = [
        'creada'                  => ['calidad'],
        'corregida'               => ['calidad'],
        'devuelta'                => ['emisor'],
        'aceptada'                => ['lider', 'emisor'],
        'reporte_enviado'         => ['calidad'],
        'reporte_devuelto'        => ['lider'],
        'reporte_aprobado'        => ['lider'],
        'acciones_capturadas'     => ['lider'],
        'evidencia_enviada'       => ['calidad'],
        'accion_rechazada'        => ['lider'],
        'implementacion_completa' => ['lider', 'emisor'],
        'verificada_efectiva'     => ['emisor', 'lider'],
        'verificada_no_efectiva'  => ['emisor', 'lider'],
        'creada_por_no_efectiva'  => ['lider'],
    ];

    public static function forLog(NcLog $log): void
    {
        $roles = self::RECIPIENTS[$log->event] ?? null;

        if (! $roles) {
            return;
        }

        $nc = NonConformity::with(['leader', 'issuer'])->find($log->non_conformity_id);

        $recipients = collect($roles)
            ->flatMap(fn (string $role) => self::resolve($nc, $role))
            ->filter(fn (?User $u) => $u && $u->active && $u->id !== $log->user_id) // nunca a quien hizo la acción
            ->unique('id')
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NcActivity($nc, $log->eventLabel(), $log->comment, $log->event));
        }
    }

    public static function quality(): Collection
    {
        return User::where('role', 'calidad')->where('active', true)->get();
    }

    protected static function resolve(NonConformity $nc, string $role): Collection
    {
        return match ($role) {
            'calidad' => self::quality(),
            'lider'   => collect([$nc->leader]),
            'emisor'  => collect([$nc->issuer]),
            default   => collect(),
        };
    }
}