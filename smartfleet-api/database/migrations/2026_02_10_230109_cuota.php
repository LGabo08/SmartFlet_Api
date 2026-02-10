<?php

// database/migrations/xxxx_xx_xx_create_cuotas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCuotasTable extends Migration
{
    public function up()
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id('idCuota');  // ID de la cuota
            $table->decimal('cuotaMaxima', 8, 2);  // Cuota máxima permitida para el operador
            $table->decimal('cuotaRestante', 8, 2);  // Cuota restante para el operador
            $table->timestamps();  // timestamps para created_at y updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('cuotas');
    }
}
