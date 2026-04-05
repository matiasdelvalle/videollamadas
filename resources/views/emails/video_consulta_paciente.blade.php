<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <h2>Videollamada médica</h2>
    <p>Su consulta fue programada correctamente.</p>
    <p><strong>Fecha:</strong> {{ $consulta->inicio_programado->format('d/m/Y H:i') }}</p>
    <p>Para ingresar a la consulta haga clic en el siguiente enlace:</p>
    <p><a href="{{ url('/paciente/' . $consulta->token_paciente) }}">Ingresar a la videollamada</a></p>
    <p>Por favor ingrese unos minutos antes del horario indicado.</p>
</body>
</html>