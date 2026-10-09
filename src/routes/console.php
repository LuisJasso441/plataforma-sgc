<?php

use App\Enums\NcActionStatus;
use App\Enums\NcStage;
use App\Enums\NcStatus;
use App\Models\NcAction;
use App\Models\NonConformity;
use App\Models\User;
use App\Notifications\NcDailyDigest;
use App\Support\NcNotifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;


/**
 * No Conformidad: recalcula el estatus de bitácora (Abierta / Vencida)
 * de todas las NC que no están cerradas.
 */
Artisan::command('nc:refresh-status', function () {
    $changed = 0;

    NonConformity::whereNotIn('stage', [NcStage::Cerrada->value, NcStage::NoEfectiva->value])
        ->chunkById(100, function ($ncs) use (&$changed) {
            foreach ($ncs as $nc) {
                $before = $nc->status;
                $nc->refreshStatus();

                if ($nc->status !== $before) {
                    $changed++;

                    if ($nc->status === NcStatus::Vencida) {
                        $nc->log('vencida', 'Una o más acciones pasaron su fecha compromiso sin ser validadas.');
                    }
                }
            }
        });

    // Verificaciones que llegaron a su fecha: se registra una sola vez por NC
    $due = NonConformity::where('stage', NcStage::EnVerificacion->value)
        ->whereDate('verification_date', '<=', today())
        ->whereDoesntHave('logs', fn ($q) => $q->where('event', 'verificacion_pendiente'))
        ->get();

    foreach ($due as $nc) {
        $nc->log('verificacion_pendiente', "Fecha de verificación: {$nc->verification_date->format('d/m/Y')}");
    }

    $this->info("Estatus recalculados. NC con cambio: {$changed}. Verificaciones pendientes nuevas: {$due->count()}");
})->purpose('Recalcula el estatus de bitácora y detecta verificaciones de efectividad pendientes');

Schedule::command('nc:refresh-status')
    ->dailyAt('00:05')
    ->name('nc:refresh-status')
    ->withoutOverlapping();

/**
 * Resumen diario: un correo por persona con sus pendientes.
 *  - Líder: sus acciones vencidas y las que vencen en 3 días.
 *  - Calidad: todas las acciones vencidas y las verificaciones pendientes.
 */
Artisan::command('nc:daily-digest', function () {
    $today = today();

    $actions = NcAction::query()
        ->where('status', '!=', NcActionStatus::Validada->value)
        ->whereDate('commitment_date', '<=', $today->copy()->addDays(3))
        ->whereHas('nonConformity', fn ($q) => $q->where('stage', NcStage::EnImplementacion->value))
        ->with(['nonConformity.department', 'nonConformity.leader'])
        ->orderBy('commitment_date')
        ->get();

    $verifications = NonConformity::query()
        ->where('stage', NcStage::EnVerificacion->value)
        ->whereDate('verification_date', '<=', $today)
        ->with('department')
        ->orderBy('verification_date')
        ->get();

    $quality = NcNotifier::quality();
    $perUser = [];

    $add = function (User $user, string $section, NonConformity $nc, string $detail, $date) use (&$perUser) {
        $key = "{$nc->id}|{$detail}"; // evita repetir si alguien es Calidad y líder a la vez
        $perUser[$user->id]['user'] = $user;
        $perUser[$user->id]['sections'][$section][$key] = [
            'folio'      => $nc->folio,
            'department' => $nc->department->name,
            'detail'     => $detail,
            'date'       => $date->format('d/m/Y'),
            'url'        => route('no-conformidad.show', $nc),
        ];
    };

    foreach ($actions as $action) {
        $nc      = $action->nonConformity;
        $overdue = $action->commitment_date->lt($today);
        $section = $overdue ? 'vencidas' : 'proximas';
        $detail  = "Acción {$action->number}: " . Str::limit($action->activity, 80);

        if ($nc->leader?->active) {
            $add($nc->leader, $section, $nc, $detail, $action->commitment_date);
        }
        if ($overdue) {
            foreach ($quality as $user) {
                $add($user, $section, $nc, $detail, $action->commitment_date);
            }
        }
    }

    foreach ($verifications as $nc) {
        foreach ($quality as $user) {
            $add($user, 'verificaciones', $nc, 'Verificación de efectividad', $nc->verification_date);
        }
    }

    foreach ($perUser as ['user' => $user, 'sections' => $sections]) {
        $user->notify(new NcDailyDigest(array_map('array_values', $sections)));
    }

    $this->info('Resúmenes enviados: ' . count($perUser));
})->purpose('Envía el resumen diario de pendientes de No Conformidad');

Schedule::command('nc:daily-digest')
    ->weekdays()
    ->at('07:00')
    ->name('nc:daily-digest')
    ->withoutOverlapping();
