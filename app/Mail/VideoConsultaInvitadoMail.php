<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class VideoConsultaInvitadoMail extends Mailable
{
    public $consulta;
    public $invitado;

    public function __construct($consulta, $invitado)
    {
        $this->consulta = $consulta;
        $this->invitado = $invitado;
    }

    public function build()
    {
        return $this
            ->subject('Invitación a videollamada')
            ->view('emails.video_consulta_invitado');
    }
}