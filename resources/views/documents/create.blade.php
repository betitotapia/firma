@extends('layouts.app')

@section('content')

<div class="card">
    <div>
        <a href="{{ route('dashboard') }}">← Volver</a>
    </div>

    <h2>Nuevo contrato</h2>

    @if ($errors->any())
        <div style="margin-bottom: 15px; padding: 10px; border: 1px solid #dc3545; background: #f8d7da; color: #842029;">
            <strong>Hay errores:</strong>
            <ul style="margin: 8px 0 0 18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
        @csrf

        <label>Título</label>
        <input name="title" value="{{ old('title', 'Contrato laboral') }}" required>

        <div class="row">
            <div class="col">
                <label>Nombre firmante</label>
                <input name="recipient_name" value="{{ old('recipient_name') }}" required>

                <label>Email firmante</label>
                <input type="email" name="recipient_email" value="{{ old('recipient_email') }}" required>

                <label>Cargo del firmante</label>
                <input
                    name="recipient_display_role"
                    value="{{ old('recipient_display_role', 'Trabajador') }}"
                    placeholder="Ej. Auxiliar administrativo, Chofer, Supervisor"
                    required
                >
            </div>

            <div class="col">
                <h3>Directivo (2ª firma)</h3>

                <label>Nombre del directivo</label>
                <input name="director_name" value="{{ old('director_name') }}" required>

                <label>Email del directivo</label>
                <input type="email" name="director_email" value="{{ old('director_email') }}" required>

                <label>Cargo del directivo</label>
                <input
                    name="director_display_role"
                    value="{{ old('director_display_role', 'Patrón / Directivo') }}"
                    placeholder="Ej. Representante legal, Gerente de RRHH, Director general"
                    required
                >
            </div>
        </div>

        <label>PDF del contrato</label>
        <input type="file" name="pdf" accept="application/pdf" required>

        <div style="margin-top: 15px;">
            <button type="submit">Guardar</button>
            <a href="{{ route('dashboard') }}">Cancelar</a>
        </div>
    </form>
</div>

@endsection