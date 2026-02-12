<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsuariosTable extends Migration
{
    public function up()
{
    Schema::create('usuarios', function (Blueprint $table) {
        
    $table->id('idUsuario');  // Crea la columna `idUsuario` como clave primaria
        $table->engine = 'InnoDB';  // Asegura que ambas tablas usen InnoDB
        $table->string('email')->unique();  // Correo electrónico único
        $table->string('apellidos');  // Apellidos del usuario
        $table->string('contrasena');  // Contraseña
        $table->string('estado')->default('ACTIVO');  // Estado del usuario, por defecto 'ACTIVO'
        $table->unsignedBigInteger('role_id');  // Define role_id como unsignedBigInteger
        $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');  // Relación con la tabla `roles`
        $table->timestamps();  // Tiempos de creación y actualización
    });
}


    public function down()
    {
        Schema::dropIfExists('usuarios');
    }
}
