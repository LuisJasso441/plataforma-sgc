<?php

namespace App\Enums;

/**
 * Origen de acción (sección "b" del formato).
 */
enum NcOrigin: string
{
    case ServicioProcesoProducto = 'servicio_proceso_producto';
    case RevisionDireccion       = 'revision_direccion';
    case Cliente                 = 'cliente';
    case AuditoriaInterna        = 'auditoria_interna';
    case AuditoriaExterna        = 'auditoria_externa';

    public function label(): string
    {
        return match ($this) {
            self::ServicioProcesoProducto => 'Servicio / Proceso / Producto',
            self::RevisionDireccion       => 'Revisión por la Dirección',
            self::Cliente                 => 'Cliente',
            self::AuditoriaInterna        => 'Auditoría interna',
            self::AuditoriaExterna        => 'Auditoría externa',
        };
    }
}