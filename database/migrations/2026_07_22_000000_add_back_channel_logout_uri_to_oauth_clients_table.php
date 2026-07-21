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
            // Endpoint the OIDC Back-Channel Logout notification (a signed
            // logout_token, see App\Services\Oidc\BackChannelLogoutTokenBuilder)
            // is POSTed to when a user with a non-revoked token for this
            // client logs out of StuMV. Null (the default) means the client
            // isn't notified.
            //
            // No ->after(): see 2026_07_14_000002_add_scopes_to_oauth_clients_table.php
            // for why positioning relative to other columns isn't safe here.
            $table->string('back_channel_logout_uri')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('back_channel_logout_uri');
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
