<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acceso QR · UNCPGGL</title>
  <style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;font-family:Segoe UI,Arial,sans-serif;color:#e2e8f0}
    .card{width:min(92vw,460px);background:#1e293b;border-radius:18px;padding:28px;box-shadow:0 18px 45px rgba(0,0,0,.35)}
    h1{font-size:21px;margin:0 0 8px;color:#f8fafc}
    p{line-height:1.6;color:#cbd5e1}
    .meta{font-size:14px;color:#94a3b8;text-align:center}
    a.button{display:block;text-decoration:none;text-align:center;background:#2563eb;color:#fff;font-weight:700;border-radius:10px;padding:14px;margin-top:22px}
    a.button:hover{background:#1d4ed8}
  </style>
</head>
<body>
  <main class="card">
    <h1>Solicitud de acceso QR</h1>
    <p>Se pidió iniciar sesión en el chatbot con el carné <strong>{{ $carnet }}</strong>.</p>
    <p>Ten a mano el número que aparece en la computadora.</p>
    <p class="meta">Válido hasta {{ $expiresAt->format('H:i') }}</p>
    @if($needsGoogle)
      <p>Primero verifica tu identidad con tu cuenta Google autorizada. Después podrás autorizar o rechazar el acceso.</p>
      <a class="button" href="{{ route('qr.google.start') }}">Continuar con Google</a>
    @else
      <p>Tu identidad Google ya fue verificada. Confirma el número para continuar.</p>
      <a class="button" href="{{ route('qr.review') }}">Revisar solicitud</a>
    @endif
  </main>
</body>
</html>
