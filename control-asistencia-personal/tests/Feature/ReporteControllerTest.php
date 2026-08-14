<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_lista_como_ausente_solo_al_personal_activo_sin_asistencia_hoy(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');
        $presente = $this->crearPersonal('Presente', '00000001');
        $ausente = $this->crearPersonal('Ausente', '00000002');
        $inactivo = $this->crearPersonal('Inactivo', '00000003', 'inactivo');
        Asistencia::create([
            'personal_id' => $presente->id,
            'fecha' => '2026-08-13',
            'hora_entrada' => '07:55:00',
        ]);

        $this->get('/reportes')
            ->assertOk()
            ->assertViewHas('personalAusenteHoy', function ($personas) use ($ausente, $presente, $inactivo) {
                return $personas->pluck('id')->all() === [$ausente->id]
                    && ! $personas->contains('id', $presente->id)
                    && ! $personas->contains('id', $inactivo->id);
            })
            ->assertSee('Ausente Prueba')
            ->assertDontSee('Inactivo Prueba');
    }

    public function test_entrada_despues_de_la_hora_limite_se_marca_como_tardanza(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');
        config(['asistencia.hora_limite_tardanza' => '08:00:00']);
        $personal = $this->crearPersonal('Tardío', '00000004');
        Asistencia::create([
            'personal_id' => $personal->id,
            'fecha' => '2026-08-13',
            'hora_entrada' => '08:00:01',
        ]);

        $this->get('/reportes?desde=2026-08-13&hasta=2026-08-13')
            ->assertOk()
            ->assertViewHas('asistenciasHoy', fn ($asistencias) => $asistencias->sole()->es_tardanza === true)
            ->assertViewHas('resumen', fn (array $resumen) => $resumen['tardanzas'] === 1)
            ->assertSee('Tardanza')
            ->assertSee('08:00:01');
    }

    public function test_filtro_de_fechas_limita_el_historial_y_calcula_el_porcentaje(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');
        $personal = $this->crearPersonal('Historial', '00000005');
        Asistencia::create(['personal_id' => $personal->id, 'fecha' => '2026-08-12', 'hora_entrada' => '07:50:00']);
        Asistencia::create(['personal_id' => $personal->id, 'fecha' => '2026-08-13', 'hora_entrada' => '07:50:00']);

        $this->get('/reportes?desde=2026-08-13&hasta=2026-08-13')
            ->assertOk()
            ->assertViewHas('historial', fn ($historial) => $historial->count() === 1 && $historial->sole()->fecha->toDateString() === '2026-08-13')
            ->assertViewHas('resumen', fn (array $resumen) => $resumen['porcentaje_asistencia'] === 100.0 && $resumen['dias_habiles'] === 1);
    }

    private function crearPersonal(string $nombres, string $dni, string $estado = 'activo'): Personal
    {
        return Personal::create([
            'nombres' => $nombres,
            'apellidos' => 'Prueba',
            'dni' => $dni,
            'cargo' => 'Docente',
            'estado' => $estado,
        ]);
    }
}
