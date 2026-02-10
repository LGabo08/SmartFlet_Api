<?php

// database/migrations/xxxx_xx_xx_create_licencias_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLicenciasTable extends Migration
{
    public function up()
    {
        Schema::create('licencias', function (Blueprint $table) {
            $table->id('idLicencia');  // ID de la licencia
            $table->string('nombre');  // Nombre o tipo de licencia (ej. "Licencia A", "Licencia B")
            $table->string('descripcion')->nullable();  // Descripción opcional de la licencia
            $table->timestamps();  // timestamps para created_at y updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('licencias');
    }
}
