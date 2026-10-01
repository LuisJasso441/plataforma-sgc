<?php

namespace App\Enums;

/**
 * Etapa interna del flujo (decide quién puede actuar).
 */
enum NcStage: string
{
    case Solicitada        = 'solicitada';          // Calidad debe aceptar
    case DevueltaEmisor    = 'devuelta_emisor';     // El emisor corrige
    case PendienteReporte  = 'pendiente_reporte';   // Líder: reunión + subir Excel
    case ReporteEnRevision = 'reporte_en_revision'; // Calidad revisa el reporte
    case ReporteDevuelto   = 'reporte_devuelto';    // Líder sube nuevo reporte
    case CapturaAcciones   = 'captura_acciones';    // Líder captura acciones definitivas
    case EnImplementacion  = 'en_implementacion';   // Evidencias por acción
    case EnVerificacion    = 'en_verificacion';     // Espera fecha de verificación
    case Cerrada           = 'cerrada';
    case NoEfectiva        = 'no_efectiva';

    public function label(): string
    {
        return match ($this) {
            self::Solicitada        => 'Solicitada',
            self::DevueltaEmisor    => 'Devuelta al emisor',
            self::PendienteReporte  => 'Pendiente de reporte',
            self::ReporteEnRevision => 'Reporte en revisión',
            self::ReporteDevuelto   => 'Reporte devuelto',
            self::CapturaAcciones   => 'Captura de acciones',
            self::EnImplementacion  => 'En implementación',
            self::EnVerificacion    => 'En verificación',
            self::Cerrada           => 'Cerrada',
            self::NoEfectiva        => 'No efectiva',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Cerrada, self::NoEfectiva], true);
    }
}