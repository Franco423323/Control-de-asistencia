<?php

namespace Tests\Feature;

use App\Exceptions\RostroInvalidoException;
use App\Exceptions\SinPersonalEnroladoException;
use App\Models\Personal;
use App\Services\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class AsistenciaControllerTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_marcar_responde_el_personal_reconocido(): void
    {
        $this->mockFaceRecognition()
            ->shouldReceive('recognize')
            ->once()
            ->withArgs(fn (string $ruta) => is_file($ruta))
            ->andReturn([
                'reconocido' => true,
                'personal_id' => 3,
                'distancia' => 0.05,
            ]);

        $this->postJson('/asistencia/marcar', [
            'foto' => $this->foto(),
        ])->assertOk()
            ->assertExactJson(['personal_id' => 3]);
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
}
