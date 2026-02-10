<?php

// database/migrations/xxxx_xx_xx_create_clientes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientesTable extends Migration
{
    public function up()
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id('idCliente');  // ID del cliente
            $table->string('nombre');  // Nombre del cliente
            $table->string('estado');  // Estado del cliente (ej. "Activo", "Inactivo")
            $table->timestamps();  // timestamps para created_at y updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('clientes');
    }
}
