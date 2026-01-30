<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('idUsuario');
            $table->string('email')->unique();
            $table->string('apellidos');
            $table->string('contrasena');
            $table->string('estado')->default('ACTIVO');

            // 🔑 FK a roles
            $table->foreignId('role_id')
                  ->constrained('roles')
                  ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('usuarios');
    }
};