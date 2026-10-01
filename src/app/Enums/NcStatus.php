<?php

namespace App\Enums;

/**
 * Estatus de la bitácora (automático).
 */
enum NcStatus: string
{
    case Abierta    = 'abierta';
    case Vencida    = 'vencida';
    case Cerrada    = 'cerrada';
    case NoEfectiva = 'no_efectiva';

    public function label(): string
    {
        return match ($this) {
            self::Abierta    => 'Abierta',
            self::Vencida    => 'Vencida',
            self::Cerrada    => 'Cerrada',
            self::NoEfectiva => 'No efectiva',
        };
    }

    /** Color para <flux:badge> */
    public function color(): string
    {
        return match ($this) {
            self::Abierta    => 'sky',
            self::Vencida    => 'amber',
            self::Cerrada    => 'green',
            self::NoEfectiva => 'red',
        };
    }
}