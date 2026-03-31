<?php
// app/Http/Controllers/Api/ClienteController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;  // Asegúrate de tener el modelo Cliente
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    // Obtener todos los clientes
    public function index()
    {
        $clientes = Cliente::all();
        return response()->json($clientes);
    }



    public function listadoSelector()
{
    $clientes = Cliente::where('activo', true)
        ->select('id_cliente', 'nombre_cliente')
        ->orderBy('nombre_cliente')
        ->get();

    return response()->json([
        'ok'      => true,
        'clientes' => $clientes
    ]);
}
}