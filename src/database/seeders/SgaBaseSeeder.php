<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SgaBaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Catálogo de departamentos / áreas
        $departamentos = [
            'ALMACÉN DE REFACCIONES',
            'ALMACÉN DE RESIDUOS',
            'CALIDAD',
            'COMERCIAL',
            'CONTABILIDAD',
            'CRÉDITO Y COBRANZA',
            'FACTURACIÓN',
            'GESTIÓN DE TALENTO HUMANO',
            'LABORATORIO',
            'LOGÍSTICA',
            'MANTENIMIENTO',
            'NORMATIVIDAD',
            'PTAR',
            'SEGURIDAD',
            'SISTEMAS',
            'TESORERÍA',
            'VENTAS',
        ];

        foreach ($departamentos as $nombre) {
            Department::firstOrCreate(['name' => $nombre]);
        }

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
    }
}