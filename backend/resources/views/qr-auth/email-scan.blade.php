<!doctype html>
<html lang="es">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verificación por correo · UNCPGGL</title>
<main>
    <h1>Verificación por correo</h1>
    <form method="post" action="{{ route('qr.email.send', ['token' => $token], false) }}" id="email-send">
        @csrf
        <button type="submit">Enviar solicitud al correo vinculado</button>
    </form>
</main>
<script>document.getElementById('email-send').requestSubmit();</script>
</html>
