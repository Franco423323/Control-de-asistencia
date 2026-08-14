<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonalController extends Controller
{
    public function crear(): View
    {
        return view('personal.enrolar', [
            'personal' => null,
            'personas' => Personal::query()
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->get(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'digits:8', 'unique:personal,dni'],
            'cargo' => ['required', 'string', 'max:255'],
        ]);

        $personal = Personal::create($validated);

        return redirect()
            ->route('personal.enrolar', $personal)
            ->with('mensaje', 'Personal creado. Ahora registra sus 3 fotos.');
    }

    public function enrolar(Personal $personal): View
    {
        return view('personal.enrolar', [
            'personal' => $personal,
            'personas' => Personal::query()
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->get(),
        ]);
    }
}
