<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SgaBaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Catálogo de departamentos (reset limpio — entorno de desarrollo)
        // Al borrar, los usuarios de un depto eliminado quedan con department_id = null (nullOnDelete).
        Department::whereNotNull('parent_id')->delete(); // primero subdepartamentos
        Department::query()->delete();                   // luego el resto

        $departamentos = [
            'ALMACÉN', 'CALIDAD', 'COMERCIAL', 'CONTABILIDAD', 'DIRECCIÓN',
            'LABORATORIO', 'LOGÍSTICA', 'MANTENIMIENTO', 'NORMATIVIDAD', 'PTAR',
            'SEGURIDAD', 'SISTEMAS', 'TALENTO HUMANO',
        ];

        foreach ($departamentos as $nombre) {
            Department::create(['name' => $nombre, 'active' => true]);
        }

        // VENTAS es subdepartamento de COMERCIAL (comparte jefe)
        $comercial = Department::where('name', 'COMERCIAL')->first();
        Department::create(['name' => 'VENTAS', 'active' => true, 'parent_id' => $comercial?->id]);

        // 2) Usuario administrador (Sistemas)
        $sistemas = Department::where('name', 'SISTEMAS')->first();

        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name'          => 'Administrador Sistemas',
                'email'         => 'admin@sga.local',
                'password'      => Hash::make('cambiar123'),
                'role'          => 'admin',
                'department_id' => $sistemas?->id,
                'active'        => true,
            ]
        );

        // 3) Catálogo de módulos (arrancamos con No Conformidad)
        Module::firstOrCreate(
            ['key' => 'no-conformidad'],
            [
                'name'        => 'No Conformidad',
                'description' => 'Registro y seguimiento de no conformidades del SGA',
                'active'      => true,
                'order'       => 1,
            ]
        );

        // 4) Catálogos del módulo No Conformidad (procesos y subprocesos)
        $this->call(NcCatalogSeeder::class);
    }
}