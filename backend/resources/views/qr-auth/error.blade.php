<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }} · UNCPGGL</title>
  <style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;font-family:Segoe UI,Arial,sans-serif;color:#e2e8f0}
    .card{width:min(92vw,460px);background:#1e293b;border-radius:18px;padding:28px;box-shadow:0 18px 45px rgba(0,0,0,.35)}
    h1{font-size:21px;margin:0 0 12px;color:#fecaca}
    p{line-height:1.6;color:#cbd5e1}
    .box{border-left:4px solid #ef4444;padding:12px 16px;background:rgba(239,68,68,.12);border-radius:8px}
  </style>
</head>
<body>
  <main class="card">
    <div class="box">
      <h1>{{ $title }}</h1>
      <p>{{ $message }}</p>
    </div>
  </main>
</body>
</html>
