<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Personal;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReporteController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $hoy = Carbon::today();
        $desde = isset($validated['desde'])
            ? Carbon::parse($validated['desde'])->startOfDay()
            : $hoy->copy()->startOfMonth();
        $hasta = isset($validated['hasta'])
            ? Carbon::parse($validated['hasta'])->startOfDay()
            : $hoy->copy()->endOfMonth()->startOfDay();
        $horaLimiteTardanza = config('asistencia.hora_limite_tardanza', '08:00:00');

        $asistenciasHoy = Asistencia::query()
            ->with('personal:id,nombres,apellidos,cargo')
            ->whereDate('fecha', $hoy)
            ->orderBy('hora_entrada')
            ->get()
            ->each(fn (Asistencia $asistencia) => $asistencia->setAttribute(
                'es_tardanza',
                $asistencia->hora_entrada !== null && $asistencia->hora_entrada > $horaLimiteTardanza
            ));

        $personalAusenteHoy = Personal::query()
            ->where('estado', 'activo')
            ->whereDoesntHave('asistencias', function ($query) use ($hoy) {
                $query->whereDate('fecha', $hoy);
            })
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'dni', 'cargo']);

        $historial = Asistencia::query()
            ->with('personal:id,nombres,apellidos,cargo')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderByDesc('fecha')
            ->orderBy('hora_entrada')
            ->get()
            ->each(fn (Asistencia $asistencia) => $asistencia->setAttribute(
                'es_tardanza',
                $asistencia->hora_entrada !== null && $asistencia->hora_entrada > $horaLimiteTardanza
            ));

        $totalPersonalActivo = Personal::query()->where('estado', 'activo')->count();
        $diasHabiles = collect(CarbonPeriod::create($desde, $hasta))
            ->filter(fn (Carbon $fecha) => $fecha->isWeekday())
            ->count();
        $asistenciasActivasDelRango = Asistencia::query()
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereHas('personal', fn ($query) => $query->where('estado', 'activo'))
            ->count();
        $capacidadDelRango = $totalPersonalActivo * $diasHabiles;
        $porcentajeAsistencia = $capacidadDelRango > 0
            ? round(($asistenciasActivasDelRango / $capacidadDelRango) * 100, 1)
            : 0.0;

        return view('reportes.index', [
            'asistenciasHoy' => $asistenciasHoy,
            'personalAusenteHoy' => $personalAusenteHoy,
            'historial' => $historial,
            'desde' => $desde,
            'hasta' => $hasta,
            'horaLimiteTardanza' => $horaLimiteTardanza,
            'resumen' => [
                'total_personal_activo' => $totalPersonalActivo,
                'porcentaje_asistencia' => $porcentajeAsistencia,
                'tardanzas' => $historial->where('es_tardanza', true)->count(),
                'faltas_hoy' => $personalAusenteHoy->count(),
                'dias_habiles' => $diasHabiles,
            ],
        ]);
    }
}
