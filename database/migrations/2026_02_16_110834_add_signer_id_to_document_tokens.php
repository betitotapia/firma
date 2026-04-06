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
    Schema::table('document_tokens', function (Blueprint $table) {
        $table->foreignId('signer_id')->nullable()->after('document_id')
            ->constrained('document_signers')->nullOnDelete();
        $table->index(['document_id','signer_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_tokens', function (Blueprint $table) {
            //
        });
    }
};
