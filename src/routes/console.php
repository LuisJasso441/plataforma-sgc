<?php

use App\Enums\NcStage;
use App\Enums\NcStatus;
use App\Models\NonConformity;
use App\Models\User;
use App\Notifications\NcDailyDigest;
use App\Support\NcNotifier;
use App\Support\NcPendingTasks;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


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
 * Resumen diario: un correo por persona con lo mismo que ve en "Mis pendientes".
 * Candidatos: usuarios activos que son líder o emisor de una NC abierta, más Calidad.
 */
Artisan::command('nc:daily-digest', function () {
    $open = NonConformity::whereNotIn('stage', [NcStage::Cerrada->value, NcStage::NoEfectiva->value])
        ->get(['leader_id', 'issued_by']);

    $ids = $open->pluck('leader_id')->merge($open->pluck('issued_by'))->filter()->unique();

    $candidates = User::where('active', true)->whereIn('id', $ids)->get()
        ->merge(NcNotifier::quality())
        ->unique('id');

    $sent = 0;

    foreach ($candidates as $user) {
        $groups = NcPendingTasks::forDigest($user);

        if ($groups) {
            $user->notify(new NcDailyDigest($groups));
            $sent++;
        }
    }

    $this->info("Resúmenes enviados: {$sent} (usuarios revisados: {$candidates->count()})");
})->purpose('Envía el resumen diario con los pendientes de No Conformidad de cada usuario');

Schedule::command('nc:daily-digest')
    ->weekdays()
    ->at('07:00')
    ->name('nc:daily-digest')
    ->withoutOverlapping();
