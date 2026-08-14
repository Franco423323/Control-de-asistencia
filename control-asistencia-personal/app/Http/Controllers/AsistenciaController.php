<?php

namespace App\Http\Controllers;

use App\Exceptions\RostroInvalidoException;
use App\Exceptions\SinPersonalEnroladoException;
use App\Models\Asistencia;
use App\Models\RostroEncoding;
use App\Services\FaceRecognitionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AsistenciaController extends Controller
{
    public function __construct(
        private readonly FaceRecognitionService $faceRecognition
    ) {}

    public function enrolar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'personal_id' => 'required|exists:personal,id',
            'foto' => 'required|image|max:5120',
        ]);

        try {
            $result = $this->faceRecognition->enroll(
                $request->file('foto')->getRealPath()
            );

            $rostroEncoding = RostroEncoding::create([
                'personal_id' => (int) $validated['personal_id'],
                'encoding' => $result['encoding'],
            ]);

            return response()->json([
                'mensaje' => 'Rostro enrolado correctamente',
                'personal_id' => (int) $rostroEncoding->personal_id,
                'rostro_encoding_id' => $rostroEncoding->id,
            ]);
        } catch (RostroInvalidoException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 503);
        }
    }

    public function marcar(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => 'required|image|max:5120',
        ]);

        try {
            $result = $this->faceRecognition->recognize(
                $request->file('foto')->getRealPath()
            );

            if (! $result['reconocido']) {
                return response()->json([
                    'reconocido' => false,
                    'mensaje' => 'No se reconoció a nadie',
                ]);
            }

            $personalId = (int) $result['personal_id'];
            $asistencia = Asistencia::query()
                ->with('personal')
                ->where('personal_id', $personalId)
                ->whereDate('fecha', Carbon::today())
                ->first();

            if (! $asistencia) {
                $hora = now();
                $asistencia = Asistencia::create([
                    'personal_id' => $personalId,
                    'fecha' => Carbon::today()->toDateString(),
                    'hora_entrada' => $hora->format('H:i:s'),
                ])->load('personal');

                return response()->json([
                    'personal_id' => $personalId,
                    'tipo' => 'entrada',
                    'hora' => $hora->format('H:i:s'),
                    'nombre_completo' => $asistencia->personal->nombres.' '.$asistencia->personal->apellidos,
                ]);
            }

            if ($asistencia->hora_salida === null) {
                $hora = now();
                $asistencia->update(['hora_salida' => $hora->format('H:i:s')]);

                return response()->json([
                    'personal_id' => $personalId,
                    'tipo' => 'salida',
                    'hora' => $hora->format('H:i:s'),
                    'nombre_completo' => $asistencia->personal->nombres.' '.$asistencia->personal->apellidos,
                ]);
            }

            return response()->json([
                'error' => 'Ya se registró entrada y salida del día de hoy para este personal',
            ], 409);
        } catch (SinPersonalEnroladoException|RostroInvalidoException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 503);
        }
    }
}
