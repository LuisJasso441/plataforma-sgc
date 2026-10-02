<?php

use App\Enums\NcStage;
use App\Enums\NcStatus;
use App\Models\NonConformity;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

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

Schedule::command('nc:refresh-status')->dailyAt('00:05');
