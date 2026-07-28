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
            // Shown on the consent screen (resources/views/auth/oauth/authorize.blade.php)
            // alongside the client's name, so a user has more context than
            // just the name before approving access.
            //
            // "service_provider" rather than reusing the existing "provider"
            // column - that one is Passport's own (Laravel\Passport\Client),
            // used for the password grant's user provider name, an unrelated
            // concept this app doesn't use.
            //
            // No ->after(): see 2026_07_14_000002_add_scopes_to_oauth_clients_table.php
            // for why positioning relative to other columns isn't safe here.
            $table->text('description')->nullable();
            $table->string('service_provider')->nullable();

            // Filename only (stored under storage/app/public/oidc-client-logos/),
            // same convention as realm_branding.logo_id/background_id.
            $table->string('logo_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn(['description', 'service_provider', 'logo_id']);
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
