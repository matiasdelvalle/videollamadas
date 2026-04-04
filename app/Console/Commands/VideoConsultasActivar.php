<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VideoConsulta;
use Carbon\Carbon;

class VideoConsultasActivar extends Command
{
    protected $signature = 'video-consultas:activar';
    protected $description = 'Activa videollamadas 15 minutos antes del inicio programado y vence consultas viejas';

    public function handle()
    {
        $now = Carbon::now();

        // 1) pasar de inactiva -> activa cuando faltan 15 min o menos
        $activadas = VideoConsulta::query()
            ->where('estado', 'inactiva')
            ->whereNotNull('inicio_programado')
            ->where('inicio_programado', '<=', $now->copy()->addMinutes(15))
            ->update([
                'estado' => 'activa',
            ]);

        // 2) vencer consultas activas/inactivas si pasaron 2 horas del horario y nadie se conectó
        $vencidas = VideoConsulta::query()
            ->whereIn('estado', ['inactiva', 'activa'])
            ->whereNotNull('inicio_programado')
            ->where('inicio_programado', '<=', $now->copy()->subHours(2))
            ->where('paciente_conectado', false)
            ->where('medico_conectado', false)
            ->update([
                'estado' => 'vencida',
            ]);

        // 3) incompleta paciente: médico entró pero paciente nunca
        $incompletaPaciente = VideoConsulta::query()
            ->whereIn('estado', ['activa', 'en_espera'])
            ->whereNotNull('inicio_programado')
            ->where('inicio_programado', '<=', $now->copy()->subHours(2))
            ->where('medico_conectado', true)
            ->where('paciente_conectado', false)
            ->update([
                'estado' => 'incompleta_paciente',
            ]);

        // 4) incompleta médico: paciente entró pero médico nunca
        $incompletaMedico = VideoConsulta::query()
            ->whereIn('estado', ['activa', 'en_espera'])
            ->whereNotNull('inicio_programado')
            ->where('inicio_programado', '<=', $now->copy()->subHours(2))
            ->where('medico_conectado', false)
            ->where('paciente_conectado', true)
            ->update([
                'estado' => 'incompleta_medico',
            ]);

        $this->info("Activadas: {$activadas}");
        $this->info("Vencidas: {$vencidas}");
        $this->info("Incompleta paciente: {$incompletaPaciente}");
        $this->info("Incompleta médico: {$incompletaMedico}");

        return self::SUCCESS;
    }
}