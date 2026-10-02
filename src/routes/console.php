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

    $this->info("Estatus recalculados. NC con cambio: {$changed}");
})->purpose('Recalcula el estatus de bitácora de las No Conformidades');

Schedule::command('nc:refresh-status')->dailyAt('00:05');
