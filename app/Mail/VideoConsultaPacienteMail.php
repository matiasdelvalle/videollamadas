<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class VideoConsultaPacienteMail extends Mailable
{
    public $consulta;

    public function __construct($consulta)
    {
        $this->consulta = $consulta;
    }

    public function build()
    {
        return $this
            ->subject('Acceso a su videollamada')
            ->view('emails.video_consulta_paciente');
    }
}