<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
{
    Schema::create('document_signers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('document_id')->constrained()->cascadeOnDelete();

        $table->string('role', 30); // 'employee' | 'director'
        $table->unsignedTinyInteger('sign_order'); // 1,2
        $table->string('name', 240);
        $table->string('email', 240);

        $table->string('status', 20)->default('pending'); // pending|signed
        $table->timestamp('signed_at')->nullable();
        $table->string('signature_path')->nullable(); // PNG del canvas
        $table->string('signed_ip', 64)->nullable();

        $table->timestamps();

        $table->index(['document_id','sign_order']);
        $table->unique(['document_id','email','sign_order']);
    });
}

};
