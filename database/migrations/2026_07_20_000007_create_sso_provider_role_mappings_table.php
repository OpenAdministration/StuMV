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
        Schema::create('sso_provider_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('realm_sso_providers')->cascadeOnDelete();
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
        Schema::dropIfExists('sso_provider_role_mappings');
    }
};
