<?php

namespace Tests\Feature;

use App\Models\Personal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_muestra_el_formulario_sin_iniciar_la_camara(): void
    {
        $personal = $this->crearPersonal();

        $this->get('/personal/crear')
            ->assertOk()
            ->assertViewIs('personal.enrolar')
            ->assertViewHas('personal', null)
            ->assertSee('Crear o seleccionar personal')
            ->assertSee('DNI '.$personal->dni)
            ->assertDontSee('<video', false);
    }

    public function test_guardar_valida_crea_y_redirige_al_enrolamiento(): void
    {
        $response = $this->post('/personal', [
            'nombres' => 'Ana María',
            'apellidos' => 'Pérez Díaz',
            'dni' => '12345678',
            'cargo' => 'Practicante',
        ]);

        $personal = Personal::query()->sole();

        $response->assertRedirect(route('personal.enrolar', $personal))
            ->assertSessionHas('mensaje', 'Personal creado. Ahora registra sus 3 fotos.');

        $this->assertDatabaseHas('personal', [
            'nombres' => 'Ana María',
            'apellidos' => 'Pérez Díaz',
            'dni' => '12345678',
            'cargo' => 'Practicante',
        ]);
    }

    public function test_guardar_rechaza_dni_invalido_o_duplicado(): void
    {
        $this->crearPersonal();

        $this->from('/personal/crear')->post('/personal', [
            'nombres' => 'Otra',
            'apellidos' => 'Persona',
            'dni' => '87654321',
            'cargo' => 'Practicante',
        ])->assertRedirect('/personal/crear')
            ->assertSessionHasErrors('dni');

        $this->assertDatabaseCount('personal', 1);
    }

    public function test_enrolar_muestra_camara_tres_indicaciones_y_envio_secuencial(): void
    {
        $personal = $this->crearPersonal();

        $this->get("/personal/{$personal->id}/enrolar")
            ->assertOk()
            ->assertViewIs('personal.enrolar')
            ->assertViewHas('personal', fn (Personal $viewPersonal) => $viewPersonal->is($personal))
            ->assertSee('Foto 1 de 3: mira de frente a la cámara')
            ->assertSee('Foto 2 de 3: gira levemente la cabeza')
            ->assertSee('Foto 3 de 3: cambia tu expresión (sonríe o gesto distinto)')
            ->assertSee('navigator.mediaDevices.getUserMedia', false)
            ->assertSee('for (let index = 0; index < photos.length; index++)', false)
            ->assertSee('await enrollPhoto(index)', false)
            ->assertSee("fetch('http://localhost/personal/{$personal->id}/enrolar'", false);
    }

    private function crearPersonal(): Personal
    {
        return Personal::create([
            'nombres' => 'Franco Aldair',
            'apellidos' => 'Roman Moran',
            'dni' => '87654321',
            'cargo' => 'Practicante',
        ]);
    }
}
