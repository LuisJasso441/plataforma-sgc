<?php

namespace Database\Seeders;

use App\Models\NcProcess;
use App\Models\NcSubprocess;
use Illuminate\Database\Seeder;

class NcCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $procesos = [
            'Planeación Estratégica',
            'Sistema de Gestión Ambiental',
            'Ventas',
            'Recepción y Manejo de Residuos',
            'Tratamiento y/o Disposición de Residuos',
            'Control de Calidad',
            'Cumplimiento Normativo',
            'Gestión de Talento Humano',
            'Compras',
            'Mantenimiento',
            'Tecnologías de la Información',
            'Seguridad Industrial',
        ];

        foreach ($procesos as $i => $nombre) {
            NcProcess::firstOrCreate(['name' => $nombre], ['order' => $i + 1]);
        }

        // "N. A." no se siembra: subproceso vacío = N. A.
        $subprocesos = [
            'Logística',
            'Almacén de herramientas y refacciones',
            'Almacén',
            'Facturación',
        ];

        foreach ($subprocesos as $i => $nombre) {
            NcSubprocess::firstOrCreate(['name' => $nombre], ['order' => $i + 1]);
        }
    }
}