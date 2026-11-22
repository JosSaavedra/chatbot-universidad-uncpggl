<!doctype html>
<html lang="es">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Confirmar acceso · UNCPGGL</title>
<main>
    <h1>Solicitud de acceso al chatbot UNCPGGL</h1>
    <p>Carné <strong>{{ $approval->carnet }}</strong></p>
    <p>Solicitud {{ $approval->public_id }}</p>
    <p>Vence a las {{ $approval->expires_at->utc()->format('H:i:s') }} UTC.</p>
    @if(isset($message))
        <p role="alert">{{ $message }}</p>
    @endif
    <form method="post" action="{{ route('qr.email.decide', ['token' => $token], false) }}">
        @csrf
        @if($choice === 'approve')
            <p>Si iniciaste este acceso, escribe el número que ves en el dispositivo original y confirma.</p>
            <label for="confirm_code">Número de confirmación</label>
            <input id="confirm_code" name="confirm_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
            <button type="submit" name="decision" value="approve">Confirmar y autorizar</button>
        @else
            <p>Confirma que deseas rechazar esta solicitud.</p>
            <button type="submit" name="decision" value="reject">Confirmar rechazo</button>
        @endif
    </form>
</main>
</html>
