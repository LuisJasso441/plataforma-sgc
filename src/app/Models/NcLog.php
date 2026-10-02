<?php

namespace App\Models;

use App\Enums\NcStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NcLog extends Model
{
    protected $fillable = [
        'user_id', 'event', 'from_stage', 'to_stage', 'comment', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'from_stage' => NcStage::class,
            'to_stage'   => NcStage::class,
            'meta'       => 'array',
        ];
    }

    public function nonConformity(): BelongsTo
    {
        return $this->belongsTo(NonConformity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'creada'    => 'Solicitud registrada',
            'corregida' => 'Solicitud corregida y reenviada',
            'editada'   => 'Datos editados',
            'aceptada'  => 'Aceptada por Calidad',
            'devuelta'  => 'Devuelta al emisor',
            'reporte_enviado'     => 'Reporte enviado a Calidad',
            'reporte_aprobado'    => 'Reporte aprobado por Calidad',
            'reporte_devuelto'    => 'Reporte devuelto al líder',
            'acciones_capturadas' => 'Acciones definitivas registradas',
            'evidencia_enviada'       => 'Evidencia enviada a revisión',
            'accion_validada'         => 'Acción validada por Calidad',
            'accion_rechazada'        => 'Evidencia rechazada por Calidad',
            'implementacion_completa' => 'Implementación completa',
            'vencida'                 => 'NC vencida',
            default     => ucfirst(str_replace('_', ' ', $this->event)),
        };
    }
}