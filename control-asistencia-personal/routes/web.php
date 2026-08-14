<?php

use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\PersonalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/personal/{personal}/enrolar', [AsistenciaController::class, 'enrolar']);
Route::get('/personal/crear', [PersonalController::class, 'crear'])->name('personal.crear');
Route::post('/personal', [PersonalController::class, 'guardar'])->name('personal.guardar');
Route::get('/personal/{personal}/enrolar', [PersonalController::class, 'enrolar'])->name('personal.enrolar');
Route::view('/asistencia/marcar', 'asistencia.marcar');
Route::post('/asistencia/marcar', [AsistenciaController::class, 'marcar']);
