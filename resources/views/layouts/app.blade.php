<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ config('app.name','Contratos Firma') }}</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:0;background:#f6f7fb;}
    .container{max-width:980px;margin:0 auto;padding:24px;}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
    .row{display:flex;gap:12px;flex-wrap:wrap;}
    .col{flex:1 1 320px;}
    label{display:block;font-weight:600;margin:10px 0 6px;}
    input,button,select{padding:10px;border:1px solid #d1d5db;border-radius:10px;width:100%;}
    button{cursor:pointer;font-weight:700;}
    .btn{background:#111827;color:#fff;border:none;}
    .btn2{background:#fff;color:#111827;}
    .muted{color:#6b7280;font-size:14px;}
    .ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px;border-radius:10px;margin-bottom:12px;}
    .err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:10px;margin-bottom:12px;}
    a{color:#2563eb;text-decoration:none;}
    code{background:#f3f4f6;padding:2px 6px;border-radius:6px;}
    .topbar{background:#0b1220;color:#fff;padding:14px 0;}
    .topbar a{color:#fff;}
    .right{margin-left:auto;}
    .btn-icon {
  display:inline-flex;
  align-items:center;
  gap:6px;
  border:none;
  border-radius:8px;
  padding:6px 10px;
  font-size:13px;
  cursor:pointer;
  text-decoration:none;
}

.btn-green {
  background:#16a34a;
  color:white;
}

.btn-green:hover {
  background:#15803d;
}

.btn-red {
  background:#dc2626;
  color:white;
}

.btn-red:hover {
  background:#b91c1c;
}
  </style>
</head>
<body>
  <div class="topbar">
    <div class="container" style="display:flex;align-items:center;gap:12px;">
      <div><strong>Contratos Firma</strong></div>
      <div class="right">
      @if(auth()->check())
          <form action="{{ route('logout') }}" method="POST" style="display:inline;">
            @csrf
            <button class="btn2" style="width:auto;padding:8px 12px;border-radius:10px;">Salir</button>
          </form>
        @endif
      </div>
    </div>
  </div>

  <div class="container">
    @if(session('ok')) <div class="ok">{{ session('ok') }}</div> @endif
    @if($errors->any())
      <div class="err">
        <ul style="margin:0;padding-left:18px;">
          @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
      </div>
    @endif

    @yield('content')
  </div>
</body>
</html>
