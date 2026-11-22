<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Autorizar acceso · UNCPGGL</title>
  <style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;font-family:Segoe UI,Arial,sans-serif;color:#e2e8f0}
    .card{width:min(92vw,460px);background:#1e293b;border-radius:18px;padding:28px;box-shadow:0 18px 45px rgba(0,0,0,.35)}
    h1{font-size:21px;margin:0 0 8px;color:#f8fafc}
    p{line-height:1.6;color:#cbd5e1}
    .ok{border-left:4px solid #22c55e;padding:10px 14px;background:rgba(34,197,94,.12);border-radius:8px;color:#bbf7d0}
    label{display:block;font-size:14px;color:#94a3b8;margin-bottom:6px}
    input{width:100%;box-sizing:border-box;padding:12px;border-radius:9px;border:1px solid #475569;background:#0f172a;color:#f8fafc;font:inherit;letter-spacing:.18em;text-align:center}
    .actions{display:flex;gap:12px;margin-top:22px}
    button{flex:1;border:0;border-radius:10px;padding:14px;font:inherit;font-weight:700;cursor:pointer;color:#fff}
    .yes{background:#15803d}.no{background:#b91c1c}
  </style>
</head>
<body>
  <main class="card">
    <h1>Confirma la solicitud</h1>
    <div class="ok">Identidad Google verificada correctamente.</div>
    <p>Carné solicitado: <strong>{{ $carnet }}</strong></p>
    <p>Válido hasta {{ $expiresAt->format('H:i') }}. Escribe el número que aparece en tu computadora.</p>

    <form method="POST" action="{{ route('qr.approve') }}">
      @csrf
      <label for="confirm_code">Escribe el número de confirmación</label>
      <input id="confirm_code" name="confirm_code" inputmode="numeric" maxlength="6" autocomplete="off" required>
      <button class="yes" type="submit" style="width:100%;margin-top:18px">Autorizar acceso</button>
    </form>

    <div class="actions">
      <form method="POST" action="{{ route('qr.reject') }}" style="flex:1">
        @csrf
        <button class="no" type="submit" style="width:100%">Rechazar</button>
      </form>
    </div>
  </main>
</body>
</html>
