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
    Schema::table('users', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->boolean('can_send')->default(true)->after('password');
        $table->boolean('can_delete')->default(false)->after('can_send');
        $table->boolean('can_manage_users')->default(false)->after('can_delete');
    });
}

public function down(): void
{
    Schema::table('users', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->dropColumn(['can_send','can_delete','can_manage_users']);
    });
}
};
