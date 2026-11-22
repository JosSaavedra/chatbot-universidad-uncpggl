<h1>Solicitud de acceso al chatbot UNCPGGL</h1>
<p>Carné <strong>{{ $approval->carnet }}</strong></p>
<p>Solicitud {{ $approval->public_id }}</p>
<p>Vence a las {{ $approval->expires_at->utc()->format('H:i:s') }} UTC.</p>
<p>Autoriza únicamente si acabas de iniciar este acceso. Ten a mano el número de confirmación que muestra el chatbot en el dispositivo donde iniciaste sesión.</p>
<p><a href="{{ $reviewUrl }}">Autorizar acceso</a> · <a href="{{ $reviewUrl }}?opcion=rechazar">Rechazar acceso</a></p>
