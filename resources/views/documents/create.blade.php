@extends('layouts.app')

@section('content')
<div class="card">
  <div>
      <a href="{{ route('dashboard') }}">← Volver</a>
    </div>
  <h2>Nuevo contrato</h2>
  <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
    @csrf
    <label>Título</label>
    <input name="title" value="{{ old('title','Contrato laboral') }}" required>

    <div class="row">
      <div class="col">
        <label>Nombre firmante</label>
        <input name="recipient_name" value="{{ old('recipient_name') }}" required>
      </div>
      <div class="col">
        <label>Email firmante</label>
        <input type="email" name="recipient_email" value="{{ old('recipient_email') }}" required>
      </div>
    </div>
        <hr style="margin:18px 0;opacity:.2;">

        <h3 style="margin:0 0 8px 0;">Directivo (2ª firma)</h3>

        <label>Nombre del directivo</label>
        <input type="text" name="director_name" value="{{ old('director_name') }}" required>

        <label>Email del directivo</label>
        <input type="email" name="director_email" value="{{ old('director_email') }}" required>
    <label>PDF del contrato</label>
    <input type="file" name="pdf" accept="application/pdf" required>

    <div style="margin-top:14px;">
      <button class="btn" type="submit">Guardar</button>
      <a class="btn2" href="{{ route('dashboard') }}" style="display:inline-block;margin-left:8px;width:auto;padding:10px 12px;border-radius:10px;border:1px solid #d1d5db;">Cancelar</a>
    </div>
  </form>
</div>
@endsection
