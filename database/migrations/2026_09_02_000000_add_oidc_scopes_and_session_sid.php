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
            // Several providers only release the groups claim behind a scope
            // of their own (Okta's "groups", a dedicated Keycloak client
            // scope, a custom authentik scope mapping), so the set can't be
            // hard-coded if group mapping is to work against them at all.
            $table->string('scopes')->default('openid email profile')->after('client_secret');
        });

        Schema::table('identity_provider_sessions', function (Blueprint $table): void {
            // Nullable: not every provider issues a sid, and rows written
            // before this column existed have none either.
            $table->string('external_sid')->nullable()->after('external_sub');

            $table->index(['provider_id', 'external_sid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('identity_provider_sessions', function (Blueprint $table): void {
            $table->dropIndex(['provider_id', 'external_sid']);
            $table->dropColumn('external_sid');
        });

        Schema::table('realm_identity_providers', function (Blueprint $table): void {
            $table->dropColumn('scopes');
        });
    }
};
