<?php

namespace App\Services;

use App\Exceptions\RostroInvalidoException;
use App\Exceptions\SinPersonalEnroladoException;
use App\Models\RostroEncoding;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class FaceRecognitionService
{
    public function enroll(string $rutaImagenTemporal): array
    {
        try {
            $response = $this->sendImage('/enroll', $rutaImagenTemporal);
        } catch (ConnectionException $exception) {
            throw $this->serviceUnavailable($exception);
        }

        if ($response->status() === 422) {
            throw new RostroInvalidoException(
                $response->json('error', 'El rostro enviado no es válido')
            );
        }

        if (! $response->successful()) {
            throw $this->serviceUnavailable();
        }

        return $response->json();
    }

    public function recognize(string $rutaImagenTemporal): array
    {
        $rostros = RostroEncoding::query()
            ->whereHas('personal', fn ($query) => $query->where('estado', 'activo'))
            ->get(['personal_id', 'encoding']);

        if ($rostros->isEmpty()) {
            throw new SinPersonalEnroladoException(
                'No hay personal enrolado en el sistema'
            );
        }

        $encodingsConocidos = $rostros
            ->map(fn (RostroEncoding $rostro) => [
                'personal_id' => $rostro->personal_id,
                'encoding' => $rostro->encoding,
            ])
            ->values()
            ->all();

        try {
            $response = $this->sendImage(
                '/recognize',
                $rutaImagenTemporal,
                ['encodings_conocidos' => json_encode($encodingsConocidos, JSON_THROW_ON_ERROR)]
            );
        } catch (ConnectionException $exception) {
            throw $this->serviceUnavailable($exception);
        }

        if ($response->status() === 422) {
            throw new RostroInvalidoException(
                $response->json('error', 'El rostro enviado no es válido')
            );
        }

        if ($response->serverError()) {
            throw $this->serviceUnavailable();
        }

        return $response->json();
    }

    private function sendImage(
        string $endpoint,
        string $rutaImagenTemporal,
        array $data = []
    ): Response {
        return Http::baseUrl(config('services.face_recognition.url'))
            ->connectTimeout(5)
            ->timeout(30)
            ->attach(
                'imagen',
                file_get_contents($rutaImagenTemporal),
                basename($rutaImagenTemporal)
            )
            ->post($endpoint, $data);
    }

    private function serviceUnavailable(?Throwable $previous = null): RuntimeException
    {
        return new RuntimeException(
            'El servicio de reconocimiento no está disponible',
            previous: $previous
        );
    }
}
