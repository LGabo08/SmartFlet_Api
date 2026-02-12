<?php
// Incluir el autoloader de Composer
require 'vendor/autoload.php';

// Usar el Hash facade de Laravel
echo \Illuminate\Support\Facades\Hash::make('adminpassword');
