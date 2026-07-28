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
            // Shown as links on the consent screen (resources/views/auth/oauth/authorize.blade.php)
            // so a user can check who runs the service and its terms before
            // approving access. "terms_url"/"privacy_policy_url" cover the
            // same ground as the OpenID Connect Dynamic Client Registration
            // 1.0 metadata fields (§2) "tos_uri"/"policy_uri"; "imprint_url"
            // has no spec equivalent - it's the German "Impressum" legal
            // notice (TMG/DDG §5), which every commercial/organizational
            // website operating in Germany is legally required to provide.
            //
            // No ->after(): see 2026_07_14_000002_add_scopes_to_oauth_clients_table.php
            // for why positioning relative to other columns isn't safe here.
            $table->string('imprint_url')->nullable();
            $table->string('terms_url')->nullable();
            $table->string('privacy_policy_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn(['imprint_url', 'terms_url', 'privacy_policy_url']);
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
