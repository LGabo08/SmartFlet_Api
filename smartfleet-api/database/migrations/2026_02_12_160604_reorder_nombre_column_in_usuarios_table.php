<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReorderNombreColumnInUsuariosTable extends Migration
{
    public function up()
    {
        // Reordenar la columna 'nombre' para que esté después de 'idUsuario' y antes de 'apellidos'
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('nombre')->after('idUsuario'); // Mueve la columna 'nombre' después de 'idUsuario'
        });
    }

    public function down()
    {
        // Si revertimos, movemos la columna 'nombre' de vuelta a su posición original
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('nombre');  // Eliminar la columna 'nombre'
        });
    }
}
