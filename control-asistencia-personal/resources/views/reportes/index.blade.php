<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de asistencia</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #17202a; background: #f2f4f6; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        header { border-bottom: 1px solid #d8dee3; background: #fff; }
        header div, main { width: min(100% - 32px, 1180px); margin: 0 auto; }
        header div { display: flex; align-items: center; justify-content: space-between; min-height: 68px; gap: 16px; }
        h1 { margin: 0; font-size: 1.3rem; }
        header a { color: #17633a; font-weight: 700; }
        main { padding: 28px 0 44px; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .summary-card, .panel { border: 1px solid #d5dce1; border-radius: 7px; background: #fff; }
        .summary-card { padding: 20px; }
        .summary-card span { display: block; margin-bottom: 7px; color: #596671; font-size: .86rem; font-weight: 700; }
        .summary-card strong { font-size: 1.8rem; }
        .summary-card small { display: block; margin-top: 7px; color: #6c7882; }
        .panel { margin-bottom: 24px; padding: 22px; }
        .panel-header { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        h2 { margin: 0; font-size: 1.12rem; }
        .muted { color: #65717b; font-size: .86rem; }
        .filter { display: flex; flex-wrap: wrap; align-items: end; gap: 14px; }
        label { display: grid; gap: 6px; color: #42505c; font-size: .85rem; font-weight: 700; }
        input { min-height: 42px; padding: 7px 10px; border: 1px solid #afb9c2; border-radius: 5px; font: inherit; }
        button { min-height: 42px; padding: 0 17px; border: 1px solid #12643a; border-radius: 5px; background: #147a46; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e1e6ea; text-align: left; vertical-align: middle; }
        th { color: #4d5963; background: #f7f9fa; font-size: .78rem; letter-spacing: .02em; text-transform: uppercase; }
        td { font-size: .9rem; }
        tbody tr:last-child td { border-bottom: 0; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #e9f5ed; color: #166239; font-size: .76rem; font-weight: 800; }
        .badge-late { background: #ffebeb; color: #a42323; }
        .empty { margin: 0; padding: 20px 4px; color: #68747e; text-align: center; }
        .errors { margin: 0 0 20px; padding: 14px 18px; border: 1px solid #ebb1b1; border-radius: 6px; background: #fff4f4; color: #962727; }
        @media (max-width: 720px) { .summary { grid-template-columns: 1fr; } .panel-header { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
    <header><div><h1>Panel de reportes de asistencia</h1><a href="{{ url('/asistencia/marcar') }}">Marcar asistencia</a></div></header>
    <main>
        @if ($errors->any())<div class="errors" role="alert">{{ $errors->first() }}</div>@endif

        <section class="summary" aria-label="Resumen del rango">
            <article class="summary-card"><span>Asistencia del rango</span><strong>{{ number_format($resumen['porcentaje_asistencia'], 1) }}%</strong><small>{{ $resumen['total_personal_activo'] }} personas activas · {{ $resumen['dias_habiles'] }} días hábiles</small></article>
            <article class="summary-card"><span>Tardanzas del rango</span><strong>{{ $resumen['tardanzas'] }}</strong><small>Hora límite: {{ $horaLimiteTardanza }}</small></article>
            <article class="summary-card"><span>Faltas de hoy</span><strong>{{ $resumen['faltas_hoy'] }}</strong><small>{{ now()->translatedFormat('d/m/Y') }}</small></article>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Asistencias de hoy</h2><span class="muted">{{ $asistenciasHoy->count() }} registro(s)</span></div>
            @if ($asistenciasHoy->isEmpty())<p class="empty">Todavía no hay asistencias registradas hoy.</p>@else
                <div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Cargo</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead><tbody>
                    @foreach ($asistenciasHoy as $asistencia)<tr><td>{{ $asistencia->personal->nombres }} {{ $asistencia->personal->apellidos }}</td><td>{{ $asistencia->personal->cargo }}</td><td>{{ $asistencia->hora_entrada ?? '—' }}</td><td>{{ $asistencia->hora_salida ?? '—' }}</td><td>@if ($asistencia->es_tardanza)<span class="badge badge-late">Tardanza</span>@else<span class="badge">A tiempo</span>@endif</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Personal que faltó hoy</h2><span class="muted">Solo personal activo</span></div>
            @if ($personalAusenteHoy->isEmpty())<p class="empty">Todo el personal activo tiene asistencia registrada hoy.</p>@else
                <div class="table-wrap"><table><thead><tr><th>Nombre</th><th>DNI</th><th>Cargo</th></tr></thead><tbody>
                    @foreach ($personalAusenteHoy as $persona)<tr><td>{{ $persona->nombres }} {{ $persona->apellidos }}</td><td>{{ $persona->dni }}</td><td>{{ $persona->cargo }}</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </section>

        <section class="panel">
            <div class="panel-header"><div><h2>Historial general</h2><span class="muted">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</span></div>
                <form class="filter" method="GET" action="{{ route('reportes.index') }}"><label>Desde<input type="date" name="desde" value="{{ $desde->toDateString() }}" required></label><label>Hasta<input type="date" name="hasta" value="{{ $hasta->toDateString() }}" required></label><button type="submit">Filtrar</button></form>
            </div>
            @if ($historial->isEmpty())<p class="empty">No hay asistencias dentro del rango seleccionado.</p>@else
                <div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Nombre</th><th>Cargo</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead><tbody>
                    @foreach ($historial as $asistencia)<tr><td>{{ $asistencia->fecha->format('d/m/Y') }}</td><td>{{ $asistencia->personal->nombres }} {{ $asistencia->personal->apellidos }}</td><td>{{ $asistencia->personal->cargo }}</td><td>{{ $asistencia->hora_entrada ?? '—' }}</td><td>{{ $asistencia->hora_salida ?? '—' }}</td><td>@if ($asistencia->es_tardanza)<span class="badge badge-late">Tardanza</span>@else<span class="badge">A tiempo</span>@endif</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </section>
    </main>
</body>
</html>