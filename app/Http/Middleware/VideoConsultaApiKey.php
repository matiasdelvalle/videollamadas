<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VideoConsultaApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY');

        if (!$apiKey || $apiKey !== config('services.video_consulta.api_key')) {
            return response()->json([
                'ok' => false,
                'error' => 'No autorizado'
            ], 401);
        }

        return $next($request);
    }
}