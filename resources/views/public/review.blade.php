{{-- resources/views/public/review.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Revisión y firma</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; background:#0b1220; color:#e8eefc; margin:0; }
    .wrap { max-width: 980px; margin: 0 auto; padding: 22px; }
    .top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; }
    .badge { display:inline-block; font-size:12px; padding:6px 10px; border-radius:999px; background:#1d2a44; color:#cfe0ff; }
    .muted { color:#a8b6d8; font-size:13px; }
    .card { background:#0f1a2f; border:1px solid #1b2a4a; border-radius:16px; padding:16px; margin-top:14px; }
    .row { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-start; }
    .col { flex:1; min-width: 280px; }
    .ok { color:#67f3a1; font-weight:600; }
    .err { color:#ff7a7a; font-weight:600; }
    .btn { display:inline-flex; gap:8px; align-items:center; border:none; cursor:pointer; padding:10px 12px; border-radius:12px; font-weight:700; }
    .btn-primary { background:#2b68ff; color:#fff; }
    .btn-green { background:#15b66d; color:#082015; }
    .btn-red { background:#ff4d4d; color:#2a0b0b; }
    .btn-gray { background:#22314f; color:#d8e4ff; }
    .btn:disabled { opacity:.55; cursor:not-allowed; }
    input, select { width:100%; padding:10px 12px; border-radius:12px; border:1px solid #22345a; background:#0b1427; color:#e8eefc; }
    label { display:block; font-size:13px; color:#cfe0ff; margin:8px 0 6px; }
    hr { border:none; border-top:1px solid #1b2a4a; margin:14px 0; }
    .hint { font-size:12px; color:#9fb0d8; margin-top:6px; line-height:1.35; }
    .videoBox { background:#071022; border:1px dashed #2a3f6b; border-radius:14px; padding:10px; }
    video, canvas.preview { width:100%; border-radius:12px; background:#000; }
    .sigWrap { background:#071022; border:1px solid #2a3f6b; border-radius:14px; padding:10px; }
    canvas#sig { width:100%; height:220px; touch-action:none; background:#fff; border-radius:12px; }
    .small { font-size:12px; }
    .chip { display:inline-block; padding:6px 10px; border-radius:999px; background:#142545; border:1px solid #243a69; font-size:12px; }
  </style>
</head>
<body>
  <div class="wrap">

   @php
    $token = $token ?? request()->route('token');
    $token_status = $token_status ?? 'active';
    $otp_verified = (bool)($otp_verified ?? false);
    $liveness_ok = (bool)($liveness_ok ?? false);
    $download_ready = (bool)($download_ready ?? false);
    $download_label = $download_label ?? 'Descargar PDF';

    $signerName = $signer->name ?? 'Firmante';
    $signerRole = $signer->role ?? '';

    $roleLabel = $signer->role_label
        ?? ($signer->display_role
            ?? ($signerRole === 'director'
                ? 'Patrón / Directivo'
                : ($signerRole === 'employee' ? 'Trabajador' : 'Firmante')));
@endphp

    <div class="top">
      <div>
        <div class="badge">Contrato #{{ $document->id ?? 'N/A' }}</div>
        <h2 style="margin:10px 0 6px;">{{ $document->title ?? 'Contrato' }}</h2>
        <div class="muted">
          Firmando como: <span class="chip">{{ $roleLabel }}</span>
          <span class="muted"> — {{ $signerName }} ({{ $signer->email ?? '—' }})</span>
        </div>
      </div>
      <div>
        <div class="badge">Token: <strong>{{ $token_status }}</strong></div>
        @if(session('ok'))
          <div class="ok" style="margin-top:10px;">{{ session('ok') }}</div>
        @endif
        @if($errors->any())
          <div class="card" style="border-color:#ff4d4d;">
            <div class="err">Hay errores:</div>
            <ul class="small" style="margin:8px 0 0 18px;">
              @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
              @endforeach
            </ul>
          </div>
        @endif
      </div>
    </div>

    {{-- Token no activo --}}
    @if($token_status !== 'active')
      <div class="card">
        <div class="err">Este link ya no está activo.</div>
        <div class="hint">Si ya firmaste, solicita al administrador que te comparta el PDF correspondiente.</div>
      </div>
    @endif

    {{-- 1) Descargar para revisar --}}
<div class="card">
  <h3 style="margin:0 0 10px;">1) Descargar para revisar</h3>

  {{-- ORIGINAL (siempre) --}}
  <form method="POST" action="{{ route('public.download', ['token' => $token]) }}" style="display:inline-block;margin-right:8px;">
    @csrf
    <input type="hidden" name="type" value="original">
    <button class="btn btn-primary" type="submit" {{ ($token_status ?? 'active') === 'revoked' ? 'disabled' : '' }}>
      ⬇️ Descargar PDF original
    </button>
  </form>

  {{-- MÁS RECIENTE (opcional) --}}
  <form method="POST" action="{{ route('public.download', ['token' => $token]) }}" style="display:inline-block;margin-right:8px;">
    @csrf
    <input type="hidden" name="type" value="latest_signed">
    <button class="btn btn-gray" type="submit" {{ ($token_status ?? 'active') === 'revoked' ? 'disabled' : '' }}>
      🧾 Descargar más reciente
    </button>
  </form>

  <div style="margin-top:12px;"></div>

  {{-- INTERMEDIO (signed_employee) --}}
  @if(($canDownloadEmployee ?? false))
    <form method="POST" action="{{ route('public.download', ['token' => $token]) }}" style="display:inline-block;margin-right:8px;">
      @csrf
      <input type="hidden" name="type" value="signed_employee">
      <button class="btn btn-green" type="submit">
        ✅ Descargar PDF intermedio
      </button>
    </form>
  @endif

  {{-- FINAL (signed_final) SOLO DIRECTOR --}}
  @if(($canDownloadFinal ?? false))
    <form method="POST" action="{{ route('public.download', ['token' => $token]) }}" style="display:inline-block;">
      @csrf
      <input type="hidden" name="type" value="signed_final">
      <button class="btn btn-green" type="submit">
        🏁 Descargar PDF final
      </button>
    </form>
  @endif

  <div class="hint" style="margin-top:10px;">
    El PDF intermedio aparece cuando el trabajador ya firmó. El PDF final aparece cuando el patrón/directivo ya firmó.
  </div>
</div>
    
    {{-- 2) OTP --}}
    <div class="card">
      <h3 style="margin:0 0 10px;">2) Verificar OTP</h3>
      <div class="row">
        <div class="col">
          <form method="POST" action="{{ route('public.requestOtp', ['token' => $token]) }}">
            @csrf
            <button class="btn btn-gray" type="submit" {{ $token_status !== 'active' ? 'disabled' : '' }}>
              ✉️ Enviar OTP a mi correo
            </button>
          </form>
          <div class="hint">El OTP se envía al correo del firmante actual.</div>
        </div>
        <div class="col">
          <form method="POST" action="{{ route('public.verifyOtp', ['token' => $token]) }}">
            @csrf
            <label>Código OTP</label>
            <input name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="6 dígitos" required>
            <div style="margin-top:10px;">
              <button class="btn btn-primary" type="submit" {{ $token_status !== 'active' ? 'disabled' : '' }}>
                ✅ Verificar OTP
              </button>
            </div>
          </form>
          @if($otp_verified)
            <div class="ok" style="margin-top:10px;">OTP verificado ✔</div>
          @endif
        </div>
      </div>
    </div>

    {{-- 3) Selfie / Liveness --}}
    <div class="card">
      <h3 style="margin:0 0 10px;">3) Evidencia de vida (Selfie)</h3>

      <div class="row">
        <div class="col">
          <div class="videoBox">
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap;">
              <div class="muted">Reto: <span class="chip" id="challengeChip">—</span></div>
              <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button class="btn btn-gray" type="button" id="btnChallenge" {{ $token_status !== 'active' ? 'disabled' : '' }}>1)🔁 Generar código</button>
                <button class="btn btn-gray" type="button" id="btnCam" {{ $token_status !== 'active' ? 'disabled' : '' }}>2)📷 Activar cámara</button>
                <button class="btn btn-primary" type="button" id="btnSnap" disabled>3)🧾 Capturar</button>
              </div>
            </div>

            <div class="hint" style="margin-top:8px;">
              Toma una selfie sosteniendo el código en pantalla (o escríbelo en una hoja). Esto se guarda como evidencia privada.
            </div>

            <div style="margin-top:10px;">
              <video id="video" playsinline autoplay muted style="display:none;"></video>
              <canvas id="selfiePreview" class="preview" style="display:none;"></canvas>
            </div>

            {{-- Upload form --}}
            <form method="POST" action="{{ route('public.liveness.upload', ['token' => $token]) }}" enctype="multipart/form-data" id="selfieForm" style="margin-top:10px;">
              @csrf

              <label>Selfie (si no usas cámara, súbela manualmente)</label>
              <input type="file" name="selfie" id="selfieFile" accept="image/*" capture="user" {{ $token_status !== 'active' ? 'disabled' : '' }}>

              <label style="margin-top:10px;">
                <input type="checkbox" style="width:16px; height:16px;" name="consent_liveness" value="1" required>
                <span class="small">Acepto capturar y adjuntar mi selfie como evidencia de vida para este contrato.</span>
              </label>

              <button class="btn btn-green" type="submit" style="margin-top:10px;" {{ (!$otp_verified || $token_status !== 'active') ? 'disabled' : '' }}>
                ✅ Subir selfie
              </button>

              <div class="hint">
                Requisito: OTP verificado. Tamaño máx. sugerido: 5MB.
              </div>
            </form>

            @if($liveness_ok)
              <div class="ok" style="margin-top:10px;">Selfie registrada ✔</div>
            @else
              <div class="muted" style="margin-top:10px;">Aún no hay selfie registrada para este firmante.</div>
            @endif
          </div>
        </div>

        <div class="col">
          <div class="card" style="margin-top:0;">
            <div class="muted">
              Para que sea fuerte como evidencia:
              <ul class="small" style="margin:8px 0 0 18px; line-height:1.4;">
                <li>Buena iluminación y rostro completo.</li>
                <li>Reto visible en la foto.</li>
                <li>OTP verificado antes de capturar.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- 4) Firma --}}
    <div class="card">
      <h3 style="margin:0 0 10px;">4) Firmar (Canvas)</h3>

      <div class="row">
        <div class="col">
          <div class="sigWrap">
            <canvas id="sig"></canvas>
            <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap;">
              <button class="btn btn-gray" type="button" id="btnClear">🧽 Limpiar</button>
              <button class="btn btn-gray" type="button" id="btnUndo">↩️ Deshacer</button>
            </div>
            <div class="hint">Firma dentro del recuadro. Si usas celular/tablet, funciona con dedo.</div>
          </div>
        </div>

        <div class="col">
          <form method="POST" action="{{ route('public.signInApp', ['token' => $token]) }}" id="signForm">
            @csrf

            <input type="hidden" name="signature_data" id="signature_data">
            <label style="margin-top:0;">
              <input type="checkbox" name="accept" value="1" required>
              <span class="small">Acepto firmar electrónicamente este contrato.</span>
            </label>

            <button class="btn btn-green" type="submit"
              {{ (!$otp_verified || !$liveness_ok || $token_status !== 'active') ? 'disabled' : '' }}>
              ✍️ Firmar y generar PDF
            </button>

            <div class="hint" style="margin-top:8px;">
              Requisitos para firmar: OTP verificado + selfie registrada.
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>

<script>
(function(){
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  // ---------------------------
  // LIVENESS (challenge + camera + capture)
  // ---------------------------
  const btnChallenge = document.getElementById('btnChallenge');
  const btnCam = document.getElementById('btnCam');
  const btnSnap = document.getElementById('btnSnap');
  const chip = document.getElementById('challengeChip');
  const video = document.getElementById('video');
  const selfiePreview = document.getElementById('selfiePreview');
  const selfieFile = document.getElementById('selfieFile');

  let stream = null;
  let challenge = null;

  async function fetchChallenge(){
    const res = await fetch("{{ route('public.liveness.challenge', ['token' => $token]) }}", {
      method: "POST",
      headers: {
        "X-CSRF-TOKEN": csrf,
        "Accept": "application/json"
      }
    });
    const data = await res.json().catch(()=> ({}));
    if (!data.ok) throw new Error(data.message || 'No se pudo generar reto');
    challenge = data.challenge;
    chip.textContent = challenge;
  }

  btnChallenge?.addEventListener('click', async () => {
    try {
      await fetchChallenge();
    } catch (e) {
      alert(e.message || 'Error generando reto');
    }
  });

  btnCam?.addEventListener('click', async () => {
    try {
      if (!challenge) await fetchChallenge();

      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: "user" },
        audio: false
      });

      video.srcObject = stream;
      video.style.display = "block";
      selfiePreview.style.display = "none";
      btnSnap.disabled = false;
    } catch (e) {
      alert("No se pudo acceder a la cámara. Puedes subir la selfie manualmente.\n\n" + (e.message || e));
    }
  });

  btnSnap?.addEventListener('click', async () => {
    if (!video || !stream) return;

    // Captura frame y lo pasa a canvas preview
    const w = video.videoWidth || 720;
    const h = video.videoHeight || 1280;

    selfiePreview.width = w;
    selfiePreview.height = h;

    const ctx = selfiePreview.getContext('2d');
    ctx.drawImage(video, 0, 0, w, h);

    // Pinta el reto como overlay (por si no lo escriben)
    ctx.fillStyle = "rgba(0,0,0,0.55)";
    ctx.fillRect(0, 0, w, 90);
    ctx.fillStyle = "#fff";
    ctx.font = "bold 44px system-ui, Arial";
    ctx.fillText("RETO: " + (challenge || "—"), 22, 62);

    selfiePreview.style.display = "block";
    video.style.display = "none";

    // Detener cámara
    stream.getTracks().forEach(t => t.stop());
    stream = null;

    // Convertir a Blob y asignarlo al input file usando DataTransfer
    selfiePreview.toBlob((blob) => {
      if (!blob) return;
      const file = new File([blob], "selfie.png", { type: "image/png" });
      const dt = new DataTransfer();
      dt.items.add(file);
      selfieFile.files = dt.files;
    }, "image/png", 0.92);
  });

  // ---------------------------
  // SIGNATURE CANVAS (pointer accurate + hiDPI)
  // ---------------------------
  const canvas = document.getElementById('sig');
  const btnClear = document.getElementById('btnClear');
  const btnUndo = document.getElementById('btnUndo');
  const signForm = document.getElementById('signForm');
  const signatureData = document.getElementById('signature_data');

  const strokes = [];
  let drawing = false;
  let current = [];

  function resizeCanvas(){
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;

    // Mantén la altura visual (CSS) pero ajusta el buffer interno
    canvas.width = Math.floor(rect.width * dpr);
    canvas.height = Math.floor(rect.height * dpr);

    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0); // dibujar en coords CSS
    redraw();
  }

  function getPoint(e){
    const rect = canvas.getBoundingClientRect();
    const x = (e.clientX - rect.left);
    const y = (e.clientY - rect.top);
    return {x, y};
  }

  function redraw(){
    const ctx = canvas.getContext('2d');
    const rect = canvas.getBoundingClientRect();
    ctx.clearRect(0, 0, rect.width, rect.height);

    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.strokeStyle = "#111";
    ctx.lineWidth = 2.2;

    for (const s of strokes){
      if (!s.length) continue;
      ctx.beginPath();
      ctx.moveTo(s[0].x, s[0].y);
      for (let i=1;i<s.length;i++){
        ctx.lineTo(s[i].x, s[i].y);
      }
      ctx.stroke();
    }

    // current stroke (si está dibujando)
    if (current.length){
      ctx.beginPath();
      ctx.moveTo(current[0].x, current[0].y);
      for (let i=1;i<current.length;i++){
        ctx.lineTo(current[i].x, current[i].y);
      }
      ctx.stroke();
    }
  }

  canvas.addEventListener('pointerdown', (e) => {
    e.preventDefault();
    canvas.setPointerCapture(e.pointerId);
    drawing = true;
    current = [];
    current.push(getPoint(e));
    redraw();
  });

  canvas.addEventListener('pointermove', (e) => {
    if (!drawing) return;
    current.push(getPoint(e));
    redraw();
  });

  function endStroke(){
    if (!drawing) return;
    drawing = false;
    if (current.length > 2) strokes.push(current);
    current = [];
    redraw();
  }

  canvas.addEventListener('pointerup', (e) => { e.preventDefault(); endStroke(); });
  canvas.addEventListener('pointercancel', (e) => { e.preventDefault(); endStroke(); });
  canvas.addEventListener('pointerleave', (e) => { /* no-op */ });

  btnClear?.addEventListener('click', () => {
    strokes.length = 0;
    current = [];
    redraw();
  });

  btnUndo?.addEventListener('click', () => {
    strokes.pop();
    redraw();
  });

  // Al enviar firma, genera PNG dataURL con fondo blanco
  signForm?.addEventListener('submit', (e) => {
    // valida que haya trazos
    if (strokes.length === 0) {
      e.preventDefault();
      alert("Firma vacía. Traza tu firma en el recuadro.");
      return;
    }

    // Render a un canvas export (fondo blanco, sin escala rara)
    const rect = canvas.getBoundingClientRect();
    const exportCanvas = document.createElement('canvas');
    exportCanvas.width = Math.floor(rect.width);
    exportCanvas.height = Math.floor(rect.height);

    const ctx = exportCanvas.getContext('2d');
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);

    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.strokeStyle = "#111";
    ctx.lineWidth = 2.2;

    for (const s of strokes){
      if (!s.length) continue;
      ctx.beginPath();
      ctx.moveTo(s[0].x, s[0].y);
      for (let i=1;i<s.length;i++){
        ctx.lineTo(s[i].x, s[i].y);
      }
      ctx.stroke();
    }

    signatureData.value = exportCanvas.toDataURL("image/png");
  });

  window.addEventListener('resize', resizeCanvas);
  // Inicial
  resizeCanvas();

})();
</script>
</body>
</html>