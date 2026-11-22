<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }} · UNCPGGL</title>
  <style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;font-family:Segoe UI,Arial,sans-serif;color:#e2e8f0}
    .card{width:min(92vw,460px);background:#1e293b;border-radius:18px;padding:28px;box-shadow:0 18px 45px rgba(0,0,0,.35)}
    h1{font-size:21px;margin:0 0 12px;color:#f8fafc}
    p{line-height:1.6;color:#cbd5e1}
    .done{border-left:4px solid #3b82f6;padding:12px 16px;background:rgba(59,130,246,.12);border-radius:8px}
  </style>
</head>
<body>
  <main class="card">
    <div class="done">
      <h1>{{ $title }}</h1>
      @if($status === 'approved')
        <p>El acceso fue autorizado para el navegador que inició la solicitud. Puedes volver a la computadora.</p>
      @elseif($status === 'rejected')
        <p>Rechazaste la solicitud. No se creó ninguna sesión.</p>
      @elseif($status === 'expired')
        <p>El tiempo terminó. Genera un código QR nuevo desde el chatbot.</p>
      @elseif($status === 'consumed')
        <p>Esta solicitud ya se utilizó. Inicia una nueva si necesitas volver a entrar.</p>
      @else
        <p>Esta solicitud ya no está disponible.</p>
      @endif
    </div>
  </main>
</body>
</html>
