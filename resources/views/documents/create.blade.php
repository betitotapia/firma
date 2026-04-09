@extends('layouts.app')

@section('content')

<style>
    .document-form {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .document-form input,
    .document-form button,
    .document-form select {
        box-sizing: border-box;
        max-width: 100%;
    }

    .document-form input {
        display: block;
    }

    .document-form .signers-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        align-items: start;
    }

    .document-form .signer-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 16px;
        background: #fafafa;
    }

    .document-form .signer-card h3 {
        margin: 0 0 12px;
    }

    .document-form .is-hidden {
        display: none;
    }

    .document-form .actions {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .document-form .actions button,
    .document-form .actions a {
        width: auto;
    }

    @media (max-width: 768px) {
        .document-form .signers-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

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

    <form class="document-form" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
        @csrf

        <div>
            <label>¿Cuántas personas firmarán?</label>
            <select name="signer_count" id="signerCount" required>
                <option value="1" @selected(old('signer_count', '2') == '1')>1 firmante</option>
                <option value="2" @selected(old('signer_count', '2') == '2')>2 firmantes</option>
            </select>
        </div>

        <div>
            <label>Título</label>
            <input name="title" value="{{ old('title', 'Contrato laboral') }}" required>
        </div>

        <div class="signers-grid">
            <section class="signer-card">
                <h3>Firmante 1</h3>

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
            </section>

            <section class="signer-card" id="signerTwoCard">
                <h3>Directivo (2ª firma)</h3>

                <label>Nombre del directivo</label>
                <input name="director_name" id="directorName" value="{{ old('director_name') }}" required>

                <label>Email del directivo</label>
                <input type="email" name="director_email" id="directorEmail" value="{{ old('director_email') }}" required>

                <label>Cargo del directivo</label>
                <input
                    name="director_display_role"
                    id="directorDisplayRole"
                    value="{{ old('director_display_role', 'Patrón / Directivo') }}"
                    placeholder="Ej. Representante legal, Gerente de RRHH, Director general"
                    required
                >
            </section>
        </div>

        <div>
            <label>PDF del contrato</label>
            <input type="file" name="pdf" accept="application/pdf" required>
        </div>

        <div class="actions">
            <button type="submit">Guardar</button>
            <a href="{{ route('dashboard') }}">Cancelar</a>
        </div>
    </form>
</div>

<script>
    (function () {
        const signerCount = document.getElementById('signerCount');
        const signerTwoCard = document.getElementById('signerTwoCard');
        const directorFields = [
            document.getElementById('directorName'),
            document.getElementById('directorEmail'),
            document.getElementById('directorDisplayRole'),
        ];

        if (!signerCount || !signerTwoCard) {
            return;
        }

        const setDirectorRequired = (required) => {
            directorFields.forEach((field) => {
                if (!field) {
                    return;
                }

                field.required = required;

                if (!required) {
                    field.value = '';
                }
            });
        };

        const syncSignerCount = () => {
            const showSecondSigner = signerCount.value === '2';
            signerTwoCard.classList.toggle('is-hidden', !showSecondSigner);
            setDirectorRequired(showSecondSigner);
        };

        signerCount.addEventListener('change', syncSignerCount);
        syncSignerCount();
    }());
</script>

@endsection