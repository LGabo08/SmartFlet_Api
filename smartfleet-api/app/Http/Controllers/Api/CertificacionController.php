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
            'cliente' => 'required|string',
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

        $validated = $request->validate([
            'nombre_certificacion' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'cliente' => 'required|string',
        ]);

        $certificacion->update($validated);

        return $certificacion;
    }

    public function destroy($id)
    {
        Certificacion::destroy($id);

        return response()->json(['message' => 'Certificación eliminada correctamente']);
    }
}