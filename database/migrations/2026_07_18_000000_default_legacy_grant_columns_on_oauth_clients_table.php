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
        // Same class of deployment as the previous "grant_types" migration:
        // an oauth_clients table provisioned before this repo's own create
        // migration, still carrying the legacy "personal_access_client" and
        // "password_client" NOT NULL columns without a default. Now that
        // "grant_types" exists, Laravel\Passport\ClientRepository::create()
        // populates that column instead and never touches these two,
        // so inserting a new client fails on the missing NOT NULL value.
        // They're superseded by "grant_types" and only ever read by
        // Passport as a fallback when it is null, so a default is enough.
        Schema::table('oauth_clients', function (Blueprint $table): void {
            if (Schema::hasColumn('oauth_clients', 'personal_access_client')) {
                $table->boolean('personal_access_client')->default(false)->change();
            }
            if (Schema::hasColumn('oauth_clients', 'password_client')) {
                $table->boolean('password_client')->default(false)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            if (Schema::hasColumn('oauth_clients', 'personal_access_client')) {
                $table->boolean('personal_access_client')->default(null)->change();
            }
            if (Schema::hasColumn('oauth_clients', 'password_client')) {
                $table->boolean('password_client')->default(null)->change();
            }
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
