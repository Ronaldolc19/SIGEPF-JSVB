<?php

namespace App\Http\Controllers;

use App\Promociones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionSelectionController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('administrador') || $request->user()->hasRole('pasante'), 403);
        $destinations = ['personal.index' => 'personal.ver', 'expedientes.index' => 'expedientes.ver', 'incidencias.index' => 'incidencias.ver'];
        $data = $request->validate([
            'promocion' => ['required', Rule::in(Promociones::selections($request))],
            'destino' => ['nullable', Rule::in(array_keys($destinations))],
        ], ['promocion.in' => 'Esta promoción no está disponible para tu cuenta. Vuelve a abrir el calendario.']);
        $request->session()->put(Promociones::sessionKey($request), (string) $data['promocion']);
        $destination = $data['destino'] ?? 'personal.index';
        if (! $request->user()->can($destinations[$destination])) {
            $destination = 'dashboard';
        }

        return to_route($destination);
    }
}
