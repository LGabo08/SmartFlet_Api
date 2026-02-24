<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Licencia;
use Illuminate\Http\Request;

class LicenciaController extends Controller
{
    public function index()
    {
        return Licencia::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_licencia' => 'required|string|max:255',
            'descripcion_licencia' => 'required|string',
        ]);

        return Licencia::create($validated);
    }

    public function show($id)
    {
        return Licencia::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $licencia = Licencia::findOrFail($id);

        $validated = $request->validate([
            'nombre_licencia' => 'required|string|max:255',
            'descripcion_licencia' => 'required|string',
        ]);

        $licencia->update($validated);

        return $licencia;
    }

    public function destroy($id)
    {
        Licencia::destroy($id);

        return response()->json(['message' => 'Licencia eliminada correctamente']);
    }
}