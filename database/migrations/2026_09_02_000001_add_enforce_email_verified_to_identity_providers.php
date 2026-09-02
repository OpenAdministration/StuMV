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
        Schema::table('realm_identity_providers', function (Blueprint $table): void {
            // On by default: accounts are matched by email address, so a
            // provider saying it hasn't verified one is worth heeding. Some
            // providers track no verification state at all and report every
            // address as unverified, though, which is what this turns off.
            $table->boolean('enforce_email_verified')->default(true)->after('scopes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('realm_identity_providers', function (Blueprint $table): void {
            $table->dropColumn('enforce_email_verified');
        });
    }
};
