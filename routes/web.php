<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoConsultaController;

Route::get('/test', [VideoConsultaController::class, 'test']);

Route::get('/', function () {
    return Inertia::render('VideoConsultas/Index');
});

Route::get('/medico/{token}',   [VideoConsultaController::class, 'medico']);
Route::get('/paciente/{token}', [VideoConsultaController::class, 'paciente']);
Route::get('/invitado/{token}', [VideoConsultaController::class, 'invitado']);