<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <h2>Invitación a videollamada</h2>

    <p>Fue invitado a participar en una videollamada.</p>

    <p>
        <strong>Fecha:</strong>
        {{ $consulta->inicio_programado ? $consulta->inicio_programado->format('d/m/Y H:i') : '-' }}
    </p>

    <p>
        Para ingresar haga clic en el siguiente enlace:
    </p>

    <p>
        <a href="{{ url('/invitado/' . $invitado->token) }}">
            Ingresar a la videollamada
        </a>
    </p>
</body>
</html>