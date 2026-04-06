@extends('layouts.app')

@section('content')
<div class="card">
  <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
    <div>
      <h2>#{{ $document->id }} — {{ $document->title }}</h2>
      <div class="muted">Estatus: <code>{{ $document->status }}</code></div>
      <div class="muted">Firmante: {{ $document->recipient->name }} ({{ $document->recipient->email }})</div>
    </div>
    <div>
      <a href="{{ route('dashboard') }}">← Volver</a>
    </div>
  </div>
</div>

@if(session('link'))
  <div class="card">
    <h3>Link para el firmante</h3>
    <p class="muted">Copia y pega este link (expira según configuración):</p>
    <p><code style="word-break:break-all;">{{ session('link') }}</code></p>
  </div>
@endif

<div class="row">
  <div class="col">
    <div class="card">
      <h3>Acciones</h3>
      {{-- <form method="POST" action="{{ route('documents.send', $document) }}">
        @csrf
        <label>Expira en (horas)</label>
        <input type="number" name="expires_hours" value="72" min="1" max="168">
        <div style="margin-top:12px;">
          <button class="btn" type="submit">Generar link</button>
        </div>
      </form> --}}
      @auth
  @if(auth()->user()->can_send)
    <form method="POST" action="{{ route('documents.send', $document) }}">
      @csrf
      <label>Expira en (horas)</label>
      <input type="number" name="expires_hours" value="72" min="1" max="168">
      <div style="margin-top:12px;">
        <button class="btn" type="submit">Generar link</button>
      </div>
    </form>
  @else
    <p class="muted">No tienes permiso para enviar links.</p>
  @endif
@endauth


      <div style="margin-top:14px;">
        <a href="{{ route('documents.evidence.json', $document) }}">Descargar evidencia (JSON)</a>
      </div>
    </div>

    <div class="card">
      <h3>Versiones</h3>
      @foreach($document->versions as $v)
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;">
          <div>
            <div><strong>{{ $v->type }}</strong> — {{ $v->original_filename }}</div>
            <div class="muted">#{{ $v->id }} · {{ number_format($v->size_bytes/1024,1) }} KB · {{ $v->created_at }}</div>
          </div>
          <div>
            <a href="{{ route('documents.download', [$document, $v]) }}">Descargar</a>
          </div>
        </div>
      @endforeach
    </div>

  </div>

  <div class="col">
    <div class="card">
      <h3>Bitácora</h3>
      @foreach($document->events as $e)
        <div style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
          <div><strong>{{ $e->event_type }}</strong> · <span class="muted">{{ $e->occurred_at }}</span></div>
          <div class="muted">IP: {{ $e->ip }} · UA: {{ $e->user_agent }}</div>
          @if($e->metadata)
            <div class="muted"><code>{{ json_encode($e->metadata) }}</code></div>
          @endif
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection
