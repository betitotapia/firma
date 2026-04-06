<!doctype html>
<html lang="es">
<body style="font-family: Arial, sans-serif;">
  <h2>{{ $title }}</h2>
  <p>Se generó el contrato firmado ({{ $stageLabel }}).</p>

  @if(!empty($evidenceUrl))
    <p>Verificación de evidencia (QR/URL):<br>
      <a href="{{ $evidenceUrl }}">{{ $evidenceUrl }}</a>
    </p>
  @endif

  @if(!empty($withoutAttachment))
    <p><strong>Nota:</strong> el PDF no se adjuntó por tamaño. Descárgalo desde la plataforma usando el enlace de verificación o el enlace de firma.</p>
  @else
    <p>Adjunto encontrarás el PDF firmado.</p>
  @endif
</body>
</html>
