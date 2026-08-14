<?php

use App\Exceptions\RostroInvalidoException;
use App\Exceptions\SinPersonalEnroladoException;
use App\Models\Personal;
use App\Models\RostroEncoding;
use App\Services\FaceRecognitionService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-enroll', function (FaceRecognitionService $faceRecognition) {
    $personalId = request()->integer('personal_id');

    if (! Personal::query()->whereKey($personalId)->exists()) {
        return response()->json(['error' => 'El personal indicado no existe'], 404);
    }

    try {
        $result = $faceRecognition->enroll(storage_path('app/test/foto.jpg'));

        $rostroEncoding = RostroEncoding::create([
            'personal_id' => $personalId,
            'encoding' => $result['encoding'],
        ]);

        return response()->json([
            'personal_id' => $personalId,
            'rostro_encoding_id' => $rostroEncoding->id,
            'encoding' => $result['encoding'],
        ]);
    } catch (RostroInvalidoException $exception) {
        return response()->json(['error' => $exception->getMessage()], 422);
    } catch (RuntimeException $exception) {
        return response()->json(['error' => $exception->getMessage()], 503);
    }
});

Route::get('/test-recognize', function (FaceRecognitionService $faceRecognition) {
    try {
        return response()->json(
            $faceRecognition->recognize(storage_path('app/test/foto.jpg'))
        );
    } catch (SinPersonalEnroladoException|RostroInvalidoException $exception) {
        return response()->json(['error' => $exception->getMessage()], 422);
    } catch (RuntimeException $exception) {
        return response()->json(['error' => $exception->getMessage()], 503);
    }
});
