<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('document_evidences', function (Blueprint $table) {

            // PK interno
            $table->id();

            // Relación documento
            $table->foreignId('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();

            // Versión exacta del PDF firmado
            $table->foreignId('document_version_id')
                ->unique() // una evidencia por versión
                ->constrained('document_versions')
                ->cascadeOnDelete();

            // employee | final
            $table->string('stage', 20);

            // UUID público imprimible en PDF
            $table->uuid('evidence_id')->unique();

            // Hash del JSON canónico
            $table->char('evidence_sha256', 64);

            // Evidencia completa
            $table->longText('evidence_json');

            $table->timestamps();

            // búsquedas rápidas
            $table->index(['document_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_evidences');
    }
};
