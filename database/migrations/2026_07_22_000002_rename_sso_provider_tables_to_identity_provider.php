<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('realm_sso_providers', 'realm_identity_providers');
        Schema::rename('sso_provider_role_mappings', 'identity_provider_role_mappings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('identity_provider_role_mappings', 'sso_provider_role_mappings');
        Schema::rename('realm_identity_providers', 'realm_sso_providers');
    }
};
