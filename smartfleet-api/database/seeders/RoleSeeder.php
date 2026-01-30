<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            ['nombre' => 'ADMIN', 'descripcion' => 'Administrador', 'created_at'=>now(), 'updated_at'=>now()],
            ['nombre' => 'COORDINADOR', 'descripcion' => 'Coordinador', 'created_at'=>now(), 'updated_at'=>now()],
            ['nombre' => 'OPERADOR', 'descripcion' => 'Operador', 'created_at'=>now(), 'updated_at'=>now()],
        ]);
    }
}

