<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoConsulta extends Model
{
    protected $fillable = [
        'room_name',
        'paciente_id',
        'medico_id',
        'token_medico',
        'token_paciente',
        'estado',
        'inicio_programado',
        'inicio_real',
        'fin_real',
        'paciente_conectado',
        'medico_conectado',
    ];

    protected $casts = [
        'paciente_conectado' => 'boolean',
        'medico_conectado' => 'boolean',
        'inicio_programado' => 'datetime',
        'inicio_real' => 'datetime',
        'fin_real' => 'datetime',
    ];

    public function paciente(){
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function medico(){
        return $this->belongsTo(User::class, 'medico_id');
    }
}