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
        Schema::create('realm_identity_providers', function (Blueprint $table) {
            $table->id();
            $table->string('realm')->index();
            $table->string('name');
            $table->string('issuer');
            $table->string('client_id');
            $table->text('client_secret');
            $table->string('groups_claim')->default('groups');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('identity_provider_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('realm_identity_providers')->cascadeOnDelete();
            $table->string('external_group');
            $table->string('committee_dn');
            $table->string('role_cn');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_provider_role_mappings');
        Schema::dropIfExists('realm_identity_providers');
    }
};
