<?php

namespace App\Enums;

/**
 * Estado de revisión de cada acción definitiva.
 */
enum NcActionStatus: string
{
    case Pendiente  = 'pendiente';   // Sin evidencia enviada
    case EnRevision = 'en_revision'; // Evidencia enviada, Calidad revisa
    case Validada   = 'validada';    // Calidad aprobó (fija fecha real)
    case Rechazada  = 'rechazada';   // Calidad pide nuevas evidencias

    public function label(): string
    {
        return match ($this) {
            self::Pendiente  => 'Pendiente',
            self::EnRevision => 'En revisión',
            self::Validada   => 'Validada',
            self::Rechazada  => 'Rechazada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente  => 'zinc',
            self::EnRevision => 'sky',
            self::Validada   => 'green',
            self::Rechazada  => 'red',
        };
    }
}