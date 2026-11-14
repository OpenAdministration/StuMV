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
        Schema::table('realm_branding', function (Blueprint $table): void {
            $table->boolean('login_notice_enabled')->default(false)->after('background_id');
            $table->text('login_notice')->nullable()->after('login_notice_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('realm_branding', function (Blueprint $table): void {
            $table->dropColumn(['login_notice_enabled', 'login_notice']);
        });
    }
};
