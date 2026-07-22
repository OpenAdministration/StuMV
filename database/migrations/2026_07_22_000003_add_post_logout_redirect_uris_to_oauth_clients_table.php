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
            // URIs this client may be redirected back to after
            // App\Http\Controllers\Oidc\EndSessionController ends the user's
            // session (OpenID Connect RP-Initiated Logout 1.0) - mirrors
            // redirect_uris (also a JSON-array-in-text column, see Passport's
            // own create_oauth_clients_table migration). A request whose
            // post_logout_redirect_uri isn't exactly one of these is never
            // honored - that's what stops this from being an open redirect.
            //
            // No ->after(): see 2026_07_14_000002_add_scopes_to_oauth_clients_table.php
            // for why positioning relative to other columns isn't safe here.
            $table->text('post_logout_redirect_uris')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('post_logout_redirect_uris');
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
