<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoConsultaInvitado extends Model
{
    protected $fillable = [
        'video_consulta_id',
        'nombre',
        'email',
        'token',
        'estado',
        'invitado_conectado_at',
        'ultimo_envio_at',
    ];

    protected $casts = [
        'invitado_conectado_at' => 'datetime',
        'ultimo_envio_at' => 'datetime',
    ];

    public function videoConsulta()
    {
        return $this->belongsTo(VideoConsulta::class, 'video_consulta_id');
    }
}