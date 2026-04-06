<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_liveness', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('signer_id')->constrained('document_signers')->cascadeOnDelete();
            $table->foreignId('token_id')->nullable()->constrained('document_tokens')->nullOnDelete();

            $table->string('type', 20)->default('selfie'); // selfie
            $table->string('challenge', 20);               // reto corto
            $table->string('storage_disk')->default('local');
            $table->string('storage_path');                // private path
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('sha256', 64);

            $table->timestamp('captured_at')->nullable();
            $table->string('captured_ip', 64)->nullable();
            $table->string('user_agent', 200)->nullable();

            $table->timestamps();

            $table->index(['document_id','signer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_liveness');
    }
};