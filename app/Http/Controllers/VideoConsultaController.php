<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;
use App\Models\VideoConsulta;
use App\Models\VideoConsultaInvitado;

use App\Mail\VideoConsultaPacienteMail;
use App\Mail\VideoConsultaInvitadoMail;
use Illuminate\Support\Facades\Mail;


class VideoConsultaController extends Controller
{
    public function store(Request $request){
        $request->validate([
            'paciente_id' => 'nullable|integer',
            'medico_id' => 'nullable|integer',
            'inicio_programado' => 'nullable|date',
            'especialidad' => 'nullable|string|max:255',
        ]);

        $consulta = VideoConsulta::create([
            'room_name' => 'consulta_' . Str::uuid(),
            'paciente_id' => $request->paciente_id,
            'medico_id' => $request->medico_id,
            'inicio_programado' => $request->inicio_programado,
            'estado' => 'inactiva',
            'token_medico' => Str::random(40),
            'token_paciente' => Str::random(40),
            'paciente_conectado' => false,
            'medico_conectado' => false,
        ]);

        if ($request->has('email')) {
            Mail::to($request->email)->queue(
                new VideoConsultaPacienteMail($consulta)
            );
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $consulta->id,
                'room_name' => $consulta->room_name,
                'estado' => $consulta->estado,
                'inicio_programado' => $consulta->inicio_programado,
                'url_medico' => url('/medico/' . $consulta->token_medico),
                'url_paciente' => url('/paciente/' . $consulta->token_paciente),
            ],
        ]);
    }

    public function show($id){
        $consulta = VideoConsulta::with('invitados')->findOrFail($id);

        return response()->json([
            'id' => $consulta->id,
            'room_name' => $consulta->room_name,
            'estado' => $consulta->estado,
            'paciente_conectado' => (bool) $consulta->paciente_conectado,
            'medico_conectado' => (bool) $consulta->medico_conectado,
            'inicio_programado' => $consulta->inicio_programado,
            'inicio_real' => $consulta->inicio_real,
            'fin_real' => $consulta->fin_real,

            'paciente_nombre' => null,
            'medico_nombre' => null,
            'especialidad' => 'Clínica médica',

            'can_join_medico' => $consulta->estado === 'en_consulta',
            'can_join_paciente' => $consulta->estado === 'en_consulta',

            'invitados' => $consulta->invitados,
        ]);
    }

    public function medico($token){
        $consulta = VideoConsulta::where('token_medico', $token)->firstOrFail();

        if (!$consulta->medico_conectado) {
            $consulta->update([
                'medico_conectado' => true,
            ]);
        }

        return inertia('VideoConsultas/SalaMedico', [
            'consultaId' => $consulta->id,
            'accessToken' => $token,
        ]);
    }

    public function paciente($token){
        $consulta = VideoConsulta::where('token_paciente', $token)->firstOrFail();

        return inertia('VideoConsultas/SalaPaciente', [
            'consultaId' => $consulta->id,
            'accessToken' => $token,
        ]);
    }

    public function invitado($token){
        $invitado = VideoConsultaInvitado::where('token', $token)->firstOrFail();
        return inertia('VideoConsultas/SalaInvitado', [
            'invitadoId' => $invitado->id,
            'accessToken' => $token,
        ]);
    }

    public function pacienteConectado($id){
        $consulta = VideoConsulta::findOrFail($id);

        $data = [
            'paciente_conectado' => true,
        ];

        if ($consulta->estado === 'activa') {
            $data['estado'] = 'en_espera';
        }

        $consulta->update($data);

        return response()->json([
            'ok' => true,
            'estado' => $consulta->fresh()->estado,
        ]);
    }

    public function admitir($id){
        $consulta = VideoConsulta::findOrFail($id);

        $consulta->update([
            'estado' => 'en_consulta',
            'inicio_real' => $consulta->inicio_real ?: now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function finalizar($id){
        $consulta = VideoConsulta::findOrFail($id);

        $consulta->update([
            'estado' => 'finalizada',
            'fin_real' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function reintentar($id){
        return response()->json(['ok' => true]);
    }

    public function joinMedico($token){
        $consulta = VideoConsulta::where('token_medico', $token)->firstOrFail();

        if ($consulta->estado !== 'en_consulta') {
            return response()->json([
                'ok' => false,
                'error' => 'La consulta todavía no está en curso.'
            ], 403);
        }

        $jwt = $this->generarToken($consulta->room_name, 'Medico', true);

        return response()->json([
            'ok' => true,
            'data' => [
                'domain' => parse_url(config('services.jitsi.url'), PHP_URL_HOST),
                'room' => $consulta->room_name,
                'jwt' => $jwt,
            ],
        ]);
    }

    public function medicoConectado($id){
        $consulta = VideoConsulta::findOrFail($id);

        $consulta->update([
            'medico_conectado' => true,
        ]);

        return response()->json(['ok' => true]);
    }

    public function joinPaciente($token){
        $consulta = VideoConsulta::where('token_paciente', $token)->firstOrFail();

        if ($consulta->estado !== 'en_consulta') {
            return response()->json([
                'ok' => false,
                'error' => 'La consulta todavía no fue habilitada por el médico.',
            ], 409);
        }

        if (!$consulta->medico_conectado) {
            return response()->json([
                'ok' => false,
                'error' => 'El profesional todavía no ingresó a la consulta.',
            ], 409);
        }

        $jwt = $this->generarToken($consulta->room_name, 'Paciente', false);

        return response()->json([
            'ok' => true,
            'data' => [
                'domain' => parse_url(config('services.jitsi.url'), PHP_URL_HOST),
                'room' => $consulta->room_name,
                'jwt' => $jwt,
            ],
        ]);
    }

    public function joinInvitado($token){
        $invitado = VideoConsultaInvitado::with('videoConsulta')->where('token', $token)->firstOrFail();
        $consulta = $invitado->videoConsulta;

        if ($consulta->estado !== 'en_consulta') {
            return response()->json([
                'ok' => false,
                'error' => 'La consulta todavía no fue habilitada.',
            ], 409);
        }

        if (!$consulta->medico_conectado) {
            return response()->json([
                'ok' => false,
                'error' => 'El profesional todavía no ingresó a la consulta.',
            ], 409);
        }

        $jwt = $this->generarToken($consulta->room_name, $invitado->nombre ?: 'Invitado', false);

        return response()->json([
            'ok' => true,
            'data' => [
                'domain' => parse_url(config('services.jitsi.url'), PHP_URL_HOST),
                'room' => $consulta->room_name,
                'jwt' => $jwt,
            ],
        ]);
    }

    private function generarToken($room, $nombre, $esModerador = false){
        $domain = parse_url(config('services.jitsi.url'), PHP_URL_HOST);
        $appId = config('services.jitsi.app_id');
        $secret = config('services.jitsi.secret');
        $now = time();

        $payload = [
            'aud' => 'jitsi',
            'iss' => $appId,
            'sub' => $domain,
            'room' => $room,
            'iat' => $now,
            'exp' => $now + 3600,
            'context' => [
                'user' => [
                    'name' => $nombre ?: 'Invitado',
                    'email' => null,
                    // 👇 CLAVE
                    'affiliation' => $esModerador ? 'owner' : 'member',
                ],
                // 👇 también ayuda en algunos setups
                'features' => [
                    'moderator' => $esModerador,
                ],
            ],
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    public function cancelar($id){
        $consulta = VideoConsulta::findOrFail($id);

        $consulta->update([
            'estado' => 'cancelada',
            'fin_real' => $consulta->fin_real ?: now(),
        ]);

        return response()->json([
            'ok' => true,
            'estado' => 'cancelada',
        ]);
    }

    public function agregarInvitado(Request $request, $id){
        $request->validate([
            'email' => 'required|email|max:255',
            'nombre' => 'nullable|string|max:255',
        ]);

        $consulta = VideoConsulta::findOrFail($id);

        $invitado = VideoConsultaInvitado::create([
            'video_consulta_id' => $consulta->id,
            'nombre' => $request->nombre,
            'email' => $request->email,
            'token' => Str::random(60),
            'estado' => 'pendiente',
            'ultimo_envio_at' => now(),
        ]);

        Mail::to($invitado->email)->queue(new VideoConsultaInvitadoMail($consulta, $invitado));

        return response()->json([
            'ok' => true,
            'data' => $invitado,
        ]);
    }

    public function eliminarInvitado($id, $invitadoId){
        $invitado = VideoConsultaInvitado::where('video_consulta_id', $id)
            ->where('id', $invitadoId)
            ->firstOrFail();

        $invitado->delete();

        return response()->json([
            'ok' => true,
        ]);
    }



}