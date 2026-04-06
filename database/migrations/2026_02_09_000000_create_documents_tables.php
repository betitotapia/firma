<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('draft'); // draft/sent/signed
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('document_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('pending'); // pending/signed
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('type'); // original/signed
            $table->string('storage_disk')->default('local');
            $table->string('storage_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
        });

        Schema::create('document_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('recipient_id');
            $table->string('token_hash', 64)->unique();
            $table->string('purpose')->default('review_sign');
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // active/consumed/revoked
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('recipient_id')->references('id')->on('document_recipients')->onDelete('cascade');
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_token_id');
            $table->string('recipient_email');
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('document_token_id')->references('id')->on('document_tokens')->onDelete('cascade');
            $table->index(['document_token_id','recipient_email']);
        });

        Schema::create('document_hashes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->unique();
            $table->string('original_sha256', 64)->nullable();
            $table->string('signed_sha256', 64)->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
        });

        Schema::create('document_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('event_type');
            $table->timestamp('occurred_at');
            $table->string('ip')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->index(['document_id','event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_events');
        Schema::dropIfExists('document_hashes');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('document_tokens');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_recipients');
        Schema::dropIfExists('documents');
    }
};
