<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // 🔐 ADMIN fijo
        Usuario::create([
            'email'      => 'admin@smartflet.com',
            'nombre'     => 'Admin',
            'apellidos'  => 'Principal',
            'contrasena' => Hash::make('admin123'),
            'estado'     => 'activo',
            'role_id'    => 1, // ADMIN
        ]);

        // 👥 10 usuarios Coordinador
        Usuario::factory()->count(10)->create([
            'role_id' => 2
        ]);

        // 👥 5 Encargados
        Usuario::factory()->count(5)->create([
            'role_id' => 3
        ]);
    }
}
