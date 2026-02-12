<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNombreToUsuariosTable extends Migration
{
    public function up()
    {
        // Agregar la columna 'nombre' a la tabla 'usuarios'
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('nombre')->nullable(); // Agrega la columna 'nombre', puede ser nullable si lo deseas
        });
    }

    public function down()
    {
        // Eliminar la columna 'nombre' en caso de revertir la migración
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
    }
}
