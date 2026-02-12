<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolesTable extends Migration
{
    public function up()
{
    Schema::create('roles', function (Blueprint $table) {
        $table->engine = 'InnoDB';  // Asegura que ambas tablas usen InnoDB
        $table->id();  // Crea la columna `id` como clave primaria
        $table->string('nombre');  // Nombre del rol
        $table->string('descripcion')->nullable();  // Descripción del rol
        $table->timestamps();  // Tiempos de creación y actualización
    });
}


    public function down()
    {
        Schema::dropIfExists('roles');
    }
}
