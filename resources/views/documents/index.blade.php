@extends('layouts.app')
@section('content')
<div class="row">
  <div class="col">
    <div class="card">
      <h2>Contratos</h2>
      <p class="muted">Lista de contratos y estatus.</p>
      <a class="btn" href="{{ route('documents.create') }}" style="display:inline-block;padding:10px 12px;border-radius:10px;">+ Nuevo contrato</a>
    </div>

      <form method="GET" action="{{ route('dashboard') }}" style="display:flex;gap:10px;align-items:center;margin-bottom:14px;">
      <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Buscar por título o firmante (nombre/email)" style="flex:1;">
      <button class="btn" type="submit" style="width:auto;padding:8px 14px;">Buscar</button>
      @if(!empty($q))
        <a class="btn2" href="{{ route('dashboard') }}" style="width:auto;padding:8px 14px;">Limpiar</a>
      @endif
    </form>

    @foreach($documents as $d)
      <div class="card">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
          <div>
            <div><strong>#{{ $d->id }}</strong> — {{ $d->title }}</div>
            <div class="muted">Firmante: {{ optional($d->recipient)->name }} ({{ optional($d->recipient)->email }})</div>
            <div class="muted">Estatus: <code>{{ $d->status }}</code></div>
          </div>
          <div style="display:flex;gap:8px;">
            {{-- ABRIR --}}
          <a href="{{ route('documents.show', $d) }}" class="btn-icon btn-green" >
            <i class="fa-solid fa-eye"></i>
            Abrir
          </a>

          {{-- ELIMINAR --}}
          <form method="POST" action="{{ route('documents.destroy', $d) }}" 
                onsubmit="return confirm('¿Eliminar este contrato?');">
            @csrf
            @method('DELETE')
            @auth
               @if(auth()->user()->can_delete)
            <button type="submit" class="btn-icon btn-red">
              <i class="fa-solid fa-trash"></i>
              Eliminar
            </button>
             @endif
            @endauth
          </form>

        </div>
        </div>
      </div>
    @endforeach
    {{ $documents->links() }}
  </div>
</div>
@endsection
