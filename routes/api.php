<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoConsultaController;

Route::prefix('video-consultas')->group(function () {
    Route::post('/',                                    [VideoConsultaController::class, 'store'])->middleware('videoconsulta.key');
    Route::get('{id}',                                  [VideoConsultaController::class, 'show']);
    Route::post('{id}/paciente-conectado',              [VideoConsultaController::class, 'pacienteConectado']);
    Route::post('{id}/medico-conectado',                [VideoConsultaController::class, 'medicoConectado']);
    Route::post('{id}/admitir',                         [VideoConsultaController::class, 'admitir']);
    Route::post('{id}/finalizar',                       [VideoConsultaController::class, 'finalizar']);
    Route::post('{id}/cancelar',                        [VideoConsultaController::class, 'cancelar']);
    Route::post('{id}/reintentar',                      [VideoConsultaController::class, 'reintentar']);
    Route::get('medico/{token}/join',                   [VideoConsultaController::class, 'joinMedico']);
    Route::get('paciente/{token}/join',                 [VideoConsultaController::class, 'joinPaciente']);
    Route::get('invitado/{token}/join',                 [VideoConsultaController::class, 'joinInvitado']);
    Route::post('{id}/invitados',                       [VideoConsultaController::class, 'agregarInvitado']);
    Route::delete('{id}/invitados/{invitadoId}',        [VideoConsultaController::class, 'eliminarInvitado']);
    Route::post('{id}/invitados/{invitadoId}/reenviar', [VideoConsultaController::class, 'reenviarInvitado']);
});