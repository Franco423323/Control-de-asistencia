<?php

use App\Http\Controllers\AsistenciaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/personal/{personal}/enrolar', [AsistenciaController::class, 'enrolar']);
Route::post('/asistencia/marcar', [AsistenciaController::class, 'marcar']);
