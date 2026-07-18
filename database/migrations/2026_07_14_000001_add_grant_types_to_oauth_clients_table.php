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
        // Some deployments' oauth_clients table predates this repo's own
        // create_oauth_clients_table migration (it was provisioned some
        // other way, before Passport's "grant_types" column existed) and
        // is missing it entirely. Passport itself already handles a
        // missing "grant_types" column gracefully when creating clients
        // (see Laravel\Passport\ClientRepository::create()), but a
        // client-credentials client - which the new directory API's
        // clients are - needs the column to actually exist so its
        // ["client_credentials"] grant type can be persisted and later
        // read back by Client::hasGrantType().
        if (! Schema::hasColumn('oauth_clients', 'grant_types')) {
            Schema::table('oauth_clients', function (Blueprint $table): void {
                $table->text('grant_types')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('oauth_clients', 'grant_types')) {
            Schema::table('oauth_clients', function (Blueprint $table): void {
                $table->dropColumn('grant_types');
            });
        }
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
