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
        Schema::table('oauth_clients', function (Blueprint $table): void {
            // Governs App\Models\PassportClient::skipsAuthorization(): when
            // true (the default, including for every pre-existing client),
            // users of this client see the consent screen
            // (resources/views/auth/oauth/authorize.blade.php) on every
            // login. Admins opt individual clients out of it - silent
            // auto-login - by setting this to false.
            //
            // No ->after(): see 2026_07_14_000002_add_scopes_to_oauth_clients_table.php
            // for why positioning relative to other columns isn't safe here.
            $table->boolean('requires_consent')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('requires_consent');
        });
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
