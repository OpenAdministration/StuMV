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
            // Restricts which scopes a client may ever be issued a token
            // for (see Laravel\Passport\Client::hasScope() /
            // Bridge\ScopeRepository::finalizeScopes()). Null means
            // unrestricted; the directory-API client management UI always
            // sets this to a specific subset (committees/groups/users).
            $table->text('scopes')->nullable()->after('grant_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('scopes');
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
