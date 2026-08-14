<?php

namespace Tests\Feature;

use App\Exceptions\RostroInvalidoException;
use App\Exceptions\SinPersonalEnroladoException;
use App\Models\Asistencia;
use App\Models\Personal;
use App\Services\FaceRecognitionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class AsistenciaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_enrolar_guarda_el_encoding_y_responde_con_exito(): void
    {
        $personal = $this->crearPersonal();
        $this->mockFaceRecognition()
            ->shouldReceive('enroll')
            ->once()
            ->withArgs(fn (string $ruta) => is_file($ruta))
            ->andReturn(['encoding' => [0.1, 0.2, 0.3]]);

        $response = $this->postJson("/personal/{$personal->id}/enrolar", [
            'personal_id' => $personal->id,
            'foto' => $this->foto(),
        ]);

        $response->assertOk()
            ->assertJson([
                'mensaje' => 'Rostro enrolado correctamente',
                'personal_id' => $personal->id,
            ])
            ->assertJsonStructure(['rostro_encoding_id']);

        $this->assertDatabaseHas('rostros_encodings', [
            'personal_id' => $personal->id,
        ]);
    }

    public function test_enrolar_valida_el_personal_y_la_foto(): void
    {
        $this->postJson('/personal/999/enrolar')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['personal_id', 'foto']);
    }

    public function test_enrolar_responde_422_si_el_rostro_es_invalido(): void
    {
        $personal = $this->crearPersonal();
        $this->mockFaceRecognition()
            ->shouldReceive('enroll')
            ->once()
            ->andThrow(new RostroInvalidoException('No se detectó ningún rostro'));

        $this->postJson("/personal/{$personal->id}/enrolar", [
            'personal_id' => $personal->id,
            'foto' => $this->foto(),
        ])->assertUnprocessable()
            ->assertExactJson(['error' => 'No se detectó ningún rostro']);
    }

    public function test_enrolar_responde_503_si_el_servicio_no_esta_disponible(): void
    {
        $personal = $this->crearPersonal();
        $this->mockFaceRecognition()
            ->shouldReceive('enroll')
            ->once()
            ->andThrow(new RuntimeException('El servicio de reconocimiento no está disponible'));

        $this->postJson("/personal/{$personal->id}/enrolar", [
            'personal_id' => $personal->id,
            'foto' => $this->foto(),
        ])->assertServiceUnavailable()
            ->assertExactJson(['error' => 'El servicio de reconocimiento no está disponible']);
    }

    public function test_get_muestra_la_camara_y_post_procesa_en_la_misma_url(): void
    {
        $this->get('/asistencia/marcar')
            ->assertOk()
            ->assertViewIs('asistencia.marcar')
            ->assertSee('Marcar asistencia')
            ->assertSee('navigator.mediaDevices.getUserMedia', false);

        $this->mockFaceRecognition()
            ->shouldReceive('recognize')
            ->once()
            ->andReturn(['reconocido' => false]);

        $this->postJson('/asistencia/marcar', [
            'foto' => $this->foto(),
        ])->assertOk()
            ->assertExactJson([
                'reconocido' => false,
                'mensaje' => 'No se reconoció a nadie',
            ]);
    }

    public function test_primera_marca_del_dia_registra_la_entrada(): void
    {
        Carbon::setTestNow('2026-08-13 08:15:30');
        $personal = $this->crearPersonal();
        $this->simularReconocimiento($personal->id);

        $this->postJson('/asistencia/marcar', [
            'foto' => $this->foto(),
        ])->assertOk()
            ->assertExactJson([
                'personal_id' => $personal->id,
                'tipo' => 'entrada',
                'hora' => '08:15:30',
                'nombre_completo' => 'Prueba Enrolamiento',
            ]);

        $this->assertDatabaseHas('asistencias', [
            'personal_id' => $personal->id,
            'hora_entrada' => '08:15:30',
            'hora_salida' => null,
        ]);
        $this->assertSame('2026-08-13', Asistencia::sole()->fecha->toDateString());
    }

    public function test_segunda_marca_del_dia_registra_la_salida(): void
    {
        Carbon::setTestNow('2026-08-13 17:45:10');
        $personal = $this->crearPersonal();
        Asistencia::create([
            'personal_id' => $personal->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora_entrada' => '08:15:30',
        ]);
        $this->simularReconocimiento($personal->id);

        $this->postJson('/asistencia/marcar', [
            'foto' => $this->foto(),
        ])->assertOk()
            ->assertExactJson([
                'personal_id' => $personal->id,
                'tipo' => 'salida',
                'hora' => '17:45:10',
                'nombre_completo' => 'Prueba Enrolamiento',
            ]);

        $this->assertDatabaseHas('asistencias', [
            'personal_id' => $personal->id,
            'hora_entrada' => '08:15:30',
            'hora_salida' => '17:45:10',
        ]);
        $this->assertSame('2026-08-13', Asistencia::sole()->fecha->toDateString());
    }

    public function test_tercera_marca_del_dia_es_rechazada_con_conflicto(): void
    {
        Carbon::setTestNow('2026-08-13 18:00:00');
        $personal = $this->crearPersonal();
        Asistencia::create([
            'personal_id' => $personal->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora_entrada' => '08:15:30',
            'hora_salida' => '17:45:10',
        ]);
        $this->simularReconocimiento($personal->id);

        $this->postJson('/asistencia/marcar', [
            'foto' => $this->foto(),
        ])->assertConflict()
            ->assertExactJson([
                'error' => 'Ya se registró entrada y salida del día de hoy para este personal',
            ]);

        $this->assertDatabaseHas('asistencias', [
            'personal_id' => $personal->id,
            'hora_entrada' => '08:15:30',
            'hora_salida' => '17:45:10',
        ]);
        $this->assertSame('2026-08-13', Asistencia::sole()->fecha->toDateString());
    }

    public function test_marcar_responde_claramente_si_no_hay_reconocimiento(): void
    {
        $this->mockFaceRecognition()
            ->shouldReceive('recognize')
            ->once()
            ->andReturn(['reconocido' => false]);

        $this->postJson('/asistencia/marcar', [
            'foto' => $this->foto(),
        ])->assertOk()
            ->assertExactJson([
                'reconocido' => false,
                'mensaje' => 'No se reconoció a nadie',
            ]);
    }

    public function test_marcar_responde_422_si_no_hay_personal_enrolado(): void
    {
        $this->mockFaceRecognition()
            ->shouldReceive('recognize')
            ->once()
            ->andThrow(new SinPersonalEnroladoException('No hay personal enrolado en el sistema'));

        $this->postJson('/asistencia/marcar', [
            'foto' => $this->foto(),
        ])->assertUnprocessable()
            ->assertExactJson(['error' => 'No hay personal enrolado en el sistema']);
    }

    public function test_marcar_responde_503_si_el_servicio_no_esta_disponible(): void
    {
        $this->mockFaceRecognition()
            ->shouldReceive('recognize')
            ->once()
            ->andThrow(new RuntimeException('El servicio de reconocimiento no está disponible'));

        $this->postJson('/asistencia/marcar', [
            'foto' => $this->foto(),
        ])->assertServiceUnavailable()
            ->assertExactJson(['error' => 'El servicio de reconocimiento no está disponible']);
    }

    private function crearPersonal(): Personal
    {
        return Personal::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Enrolamiento',
            'dni' => '00000001',
            'cargo' => 'Docente',
        ]);
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->image('foto.jpg', 100, 100);
    }

    private function mockFaceRecognition(): MockInterface
    {
        return $this->mock(FaceRecognitionService::class);
    }

    private function simularReconocimiento(int $personalId): void
    {
        $this->mockFaceRecognition()
            ->shouldReceive('recognize')
            ->once()
            ->withArgs(fn (string $ruta) => is_file($ruta))
            ->andReturn([
                'reconocido' => true,
                'personal_id' => $personalId,
                'distancia' => 0.05,
            ]);
    }
}
