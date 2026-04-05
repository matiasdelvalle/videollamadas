<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VideoConsulta;
use Carbon\Carbon;

class VideoConsultasActivar extends Command
{
    protected $signature = 'video-consultas:activar';
    protected $description = 'Activa videollamadas 15 minutos antes del inicio programado y vence consultas viejas';

    public function handle(){
        
        $now = Carbon::now();

        $this->info("carbon: {$now}");
        $limite = $now->copy()->subHours(2);

        // activar solo las que empiezan en los próximos 15 min
        $activadas = VideoConsulta::query()
            ->where('estado', 'inactiva')
            ->whereNotNull('inicio_programado')
            ->whereBetween('inicio_programado', [$now, $now->copy()->addMinutes(15)])
            ->update([
                'estado' => 'activa',
            ]);

        // vencida: pasaron 2 horas y nadie se conectó
        $vencidas = VideoConsulta::query()
            ->whereIn('estado', ['inactiva', 'activa'])
            ->whereNotNull('inicio_programado')
            ->where('inicio_programado', '<=', $limite)
            ->where('paciente_conectado', false)
            ->where('medico_conectado', false)
            ->update([
                'estado' => 'vencida',
            ]);

        // incompleta paciente
        $incompletaPaciente = VideoConsulta::query()
            ->whereIn('estado', ['activa', 'en_espera'])
            ->whereNotNull('inicio_programado')
            ->where('inicio_programado', '<=', $limite)
            ->where('medico_conectado', true)
            ->where('paciente_conectado', false)
            ->update([
                'estado' => 'incompleta_paciente',
            ]);

        // incompleta médico
        $incompletaMedico = VideoConsulta::query()
            ->whereIn('estado', ['activa', 'en_espera'])
            ->whereNotNull('inicio_programado')
            ->where('inicio_programado', '<=', $limite)
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