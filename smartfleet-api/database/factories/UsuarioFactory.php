<?php

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition()
    {
        return [
            'email'      => $this->faker->unique()->safeEmail(),
            'nombre'     => $this->faker->firstName(),
            'apellidos'  => $this->faker->lastName(),
            'contrasena' => Hash::make('password123'), // 🔐 fija para pruebas
            'estado'     => 'activo',
            'role_id'    => 2, // Coordinador por default
        ];
    }
}
