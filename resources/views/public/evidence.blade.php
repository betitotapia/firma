<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificación de Evidencia</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#0b1220;color:#e8eefc;margin:0}
    .wrap{max-width:920px;margin:0 auto;padding:22px}
    .card{background:#0f1a2f;border:1px solid #1b2a4a;border-radius:16px;padding:16px;margin-top:14px}
    .muted{color:#a8b6d8;font-size:13px}
    code{background:#081126;padding:2px 6px;border-radius:8px;border:1px solid #1b2a4a}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:820px){.grid{grid-template-columns:1fr}}
    .ok{color:#67f3a1;font-weight:700}
  </style>
</head>
<body>
<div class="wrap">
  <h2 style="margin:0;">Verificación de evidencia</h2>
  <div class="muted">Esta página muestra la evidencia registrada para un contrato firmado electrónicamente.</div>

  <div class="card">
    <div><strong>Evidence ID:</strong> <code>{{ $evidence->evidence_id }}</code></div>
    <div style="margin-top:8px;"><strong>Evidence-SHA256:</strong> <code>{{ $evidence->evidence_sha256 }}</code></div>
    <div style="margin-top:8px;" class="muted">
      Registro: {{ optional($evidence->created_at)->format('Y-m-d H:i:s') }} · Etapa: <code>{{ $evidence->stage }}</code>
    </div>
  </div>

  <div class="card">
    <h3 style="margin:0 0 10px;">Resumen</h3>

    @php
      $doc = $payload['document'] ?? [];
      $hashes = $payload['hashes'] ?? [];
      $otp = $payload['otp'] ?? [];
      $signers = $payload['signers'] ?? [];
      $liveness = $payload['liveness'] ?? [];
    @endphp

    <div class="grid">
      <div>
        <div><strong>Documento:</strong> #{{ $doc['id'] ?? '—' }} — {{ $doc['title'] ?? '—' }}</div>
        <div style="margin-top:8px;"><strong>Etapa:</strong> <code>{{ $payload['stage'] ?? '—' }}</code></div>
        <div style="margin-top:8px;"><strong>Hash original:</strong> <code>{{ $hashes['original_sha256'] ?? '—' }}</code></div>
      </div>
      <div>
        <div><strong>OTP verificado:</strong>
          @if(($otp['verified'] ?? false) === true)
            <span class="ok">Sí</span>
          @else
            No
          @endif
        </div>
        <div style="margin-top:8px;"><strong>OTP timestamp:</strong> <code>{{ $otp['verified_at'] ?? '—' }}</code></div>
        <div style="margin-top:8px;"><strong>IP:</strong> <code>{{ data_get($payload, 'request.ip', '—') }}</code></div>
      </div>
    </div>
  </div>

  <div class="card">
    <h3 style="margin:0 0 10px;">Firmantes</h3>

    @if(empty($signers))
      <div class="muted">No hay firmantes en el payload.</div>
    @else
      <ul style="margin:0 0 0 18px; line-height:1.5;">
        @foreach($signers as $s)
          <li>
            <strong>{{ $s['role'] ?? 'signer' }}</strong> —
            {{ $s['name'] ?? '—' }} ({{ $s['email'] ?? '—' }})
            · status: <code>{{ $s['status'] ?? '—' }}</code>
            · signed_at: <code>{{ $s['signed_at'] ?? '—' }}</code>
            · signed_ip: <code>{{ $s['signed_ip'] ?? '—' }}</code>
          </li>
        @endforeach
      </ul>
    @endif
  </div>

  <div class="card">
    <h3 style="margin:0 0 10px;">Prueba de vida (Selfie)</h3>

    @if(empty($liveness))
      <div class="muted">No hay liveness registrado en la evidencia.</div>
    @else
      <ul style="margin:0 0 0 18px; line-height:1.5;">
        @foreach($liveness as $l)
          <li>
            signer_id: <code>{{ $l['signer_id'] ?? '—' }}</code>
            · challenge: <code>{{ $l['challenge'] ?? '—' }}</code>
            · selfie_sha256: <code>{{ $l['sha256'] ?? '—' }}</code>
            · captured_at: <code>{{ $l['captured_at'] ?? '—' }}</code>
          </li>
        @endforeach
      </ul>
      <div class="muted" style="margin-top:10px;">
        Nota: por privacidad, aquí mostramos hashes/metadata. Las fotos se almacenan en privado.
      </div>
    @endif
  </div>

</div>
</body>
</html>