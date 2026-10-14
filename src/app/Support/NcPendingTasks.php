<?php

namespace App\Support;

use App\Enums\NcActionStatus;
use App\Enums\NcStage;
use App\Models\NcAction;
use App\Models\NcLog;
use App\Models\NonConformity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que cada usuario tiene que atender en las No Conformidades.
 */
class NcPendingTasks
{
    /** Días de anticipación para avisar la verificación de efectividad */
    public const VERIFICATION_NOTICE_DAYS = 7;

    /** Orden y presentación de los grupos: clave => [título, icono] */
    public const GROUPS = [
        'aceptar'         => ['Solicitudes por aceptar', 'inbox-arrow-down'],
        'revisar_reporte' => ['Reportes por revisar', 'document-magnifying-glass'],
        'captura'         => ['Acciones por capturar', 'pencil-square'],
        'validar'         => ['Evidencias por validar', 'check-badge'],
        'verificar'       => ['Verificaciones de efectividad', 'scale'],
        'corregir'        => ['Solicitudes devueltas', 'arrow-uturn-left'],
        'reporte'         => ['Reportes por subir', 'arrow-up-tray'],
        'evidencias'      => ['Evidencias por subir', 'paper-clip'],
    ];

    /**
     * Grupos con pendientes (los vacíos no se incluyen).
     *
     * @return Collection<int, array{key: string, title: string, icon: string, items: Collection}>
     */
    public static function for(User $user): Collection
    {
        $items = self::items($user);

        return collect(self::GROUPS)
            ->map(fn (array $meta, string $key) => [
                'key'   => $key,
                'title' => $meta[0],
                'icon'  => $meta[1],
                'items' => $items->where('group', $key)
                    ->sortBy([
                        fn ($a, $b) => $b['overdue'] <=> $a['overdue'],
                        fn ($a, $b) => $a['since'] <=> $b['since'],
                    ])
                    ->values(),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();
    }

    /** Total para el contador del menú */
    public static function count(User $user): int
    {
        return self::items($user)->count();
    }

    /** Días antes del compromiso en que el resumen marca "Vence en N días" */
    public const DIGEST_SOON_DAYS = 3;

    /**
     * Versión en arreglos simples para el resumen diario (viaja por la cola).
     *
     * @return list<array{title: string, items: list<array{folio: string, department: string, detail: string, due: ?string, note: ?string, url: string}>}>
     */
    public static function forDigest(User $user): array
    {
        return self::for($user)->map(fn (array $group) => [
            'title' => $group['title'],
            'items' => $group['items']->map(function (array $item) {
                $nc   = $item['nc'];
                $note = null;

                if ($item['overdue']) {
                    $note = 'Vencida';
                } elseif ($item['due']) {
                    $days = (int) today()->diffInDays($item['due'], false);

                    if ($days >= 0 && $days <= self::DIGEST_SOON_DAYS) {
                        $note = $days === 0 ? 'Vence hoy' : "Vence en {$days} " . ($days === 1 ? 'día' : 'días');
                    }
                }

                return [
                    'folio'      => $nc->folio,
                    'department' => $nc->department?->name ?? '—',
                    'detail'     => $item['detail'],
                    'due'        => $item['due'] ? $item['dueLabel'] . ': ' . $item['due']->format('d/m/Y') : null,
                    'note'       => $note,
                    'url'        => route('no-conformidad.show', $nc),
                ];
            })->all(),
        ])->all();
    }

    /** Lista plana de pendientes del usuario */
    protected static function items(User $user): Collection
    {
        if ($user->isAdmin()) {
            return collect();
        }

        $calidad  = $user->isCalidad();
        $ncTable  = (new NonConformity)->getTable();
        $logTable = (new NcLog)->getTable();

        $ncs = NonConformity::query()
            ->whereNotIn('stage', [NcStage::Cerrada->value, NcStage::NoEfectiva->value])
            ->when(! $calidad, fn ($q) => $q->where(fn ($q) => $q
                ->where('leader_id', $user->id)
                ->orWhere('issued_by', $user->id)))
            ->with(['department', 'actions.evidences'])
            // Desde cuándo está en la etapa actual (último registro que la llevó ahí)
            ->withMax(['logs as stage_since' => fn ($q) => $q->reorder()
                ->whereColumn("{$logTable}.to_stage", "{$ncTable}.stage")], 'created_at')
            ->get();

        $items = collect();

        $add = function (string $group, NonConformity $nc, string $detail, Carbon $since,
            bool $overdue = false, ?Carbon $due = null, ?string $dueLabel = null) use ($items) {
            $items->push(compact('group', 'nc', 'detail', 'since', 'overdue', 'due', 'dueLabel'));
        };

        foreach ($ncs as $nc) {
            $since    = $nc->stage_since ? Carbon::parse($nc->stage_since) : $nc->updated_at;
            $isLeader = $nc->leader_id === $user->id;
            $isIssuer = $nc->issued_by === $user->id;

            switch ($nc->stage) {
                case NcStage::Solicitada:
                    if ($calidad) {
                        $add('aceptar', $nc, 'Revisar la solicitud y aceptarla o devolverla al emisor.', $since);
                    }
                    break;

                case NcStage::DevueltaEmisor:
                    if ($isIssuer) {
                        $add('corregir', $nc, 'Calidad devolvió tu solicitud: corrígela y vuelve a enviarla.', $since);
                    }
                    break;

                case NcStage::PendienteReporte:
                    if ($isLeader) {
                        $add('reporte', $nc, 'Convocar la reunión multidisciplinaria y subir el reporte.', $since);
                    }
                    break;

                case NcStage::ReporteDevuelto:
                    if ($isLeader) {
                        $add('reporte', $nc, 'Calidad devolvió el reporte: sube una nueva versión.', $since);
                    }
                    break;

                case NcStage::ReporteEnRevision:
                    if ($calidad) {
                        $add('revisar_reporte', $nc, 'Revisar el reporte y aprobarlo o devolverlo.', $since);
                    }
                    break;

                case NcStage::CapturaAcciones:
                    if ($calidad) {
                        $add('captura', $nc, 'Capturar las acciones definitivas del reporte.', $since);
                    }
                    break;

                case NcStage::EnImplementacion:
                    // Líder: acciones sin evidencia o con evidencia rechazada
                    if ($isLeader) {
                        $toUpload = $nc->actions->filter(fn (NcAction $a) => in_array(
                            $a->status, [NcActionStatus::Pendiente, NcActionStatus::Rechazada], true));

                        if ($toUpload->isNotEmpty()) {
                            $detail = $toUpload->map(fn (NcAction $a) => 'Acción '.$a->number
                                .($a->status === NcActionStatus::Rechazada ? ' (rechazada)' : ''))->implode(' · ');

                            $add('evidencias', $nc, $detail, $since,
                                $toUpload->contains(fn (NcAction $a) => $a->isOverdue()),
                                $toUpload->min('commitment_date'), 'Compromiso');
                        }
                    }

                    // Calidad: acciones con evidencia enviada
                    if ($calidad) {
                        $toReview = $nc->actions->where('status', NcActionStatus::EnRevision);

                        if ($toReview->isNotEmpty()) {
                            $sent = $toReview->map(fn (NcAction $a) => $a->evidences->first()?->created_at)
                                ->filter()->min() ?? $since;

                            $add('validar', $nc,
                                'Acción '.$toReview->pluck('number')->implode(', ').' con evidencia por validar.',
                                Carbon::parse($sent));
                        }
                    }
                    break;

                case NcStage::EnVerificacion:
                    $date = $nc->verification_date;

                    if ($calidad && $date && $date->lte(today()->addDays(self::VERIFICATION_NOTICE_DAYS))) {
                        $add('verificar', $nc, 'Evaluar la efectividad de las acciones (Paso 8).', $since,
                            $date->lt(today()), $date, 'Verificación');
                    }
                    break;

                default:
                    break;
            }
        }

        return $items;
    }
}