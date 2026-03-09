<?php


namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Certificacion;
use Illuminate\Http\Request;

class CertificacionController extends Controller
{
    public function index()
    {
        return Certificacion::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_certificacion' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'fk_cliente' => 'required|exists:cliente,id_cliente',
        ]);

        return Certificacion::create($validated);
    }

    public function show($id)
    {
        return Certificacion::findOrFail($id);
    }

    public function update(Request $request, $id)
{
    $certificacion = Certificacion::findOrFail($id);
    
    // Agregar log para verificar los datos
    \Log::info('Actualizando certificación: ', $request->all());

    $validated = $request->validate([
        'nombre_certificacion' => 'required|string|max:255',
        'descripcion' => 'required|string',
        'fk_cliente' => 'required|exists:cliente,id_cliente',
    ]);
    
    $certificacion->update($validated);

    return $certificacion;
}

    public function destroy($id)
    {
        Certificacion::destroy($id);

        return response()->json(['message' => 'Certificación eliminada correctamente']);
    }

    public function getCertificacionesPorCliente($clienteId)
    {
        // Filtrar las certificaciones por el cliente
        $certificaciones = Certificacion::where('fk_cliente', $clienteId)->get();
        
        // Devolver las certificaciones encontradas en formato JSON
        return response()->json($certificaciones);
    }
}