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
        Schema::create('identity_provider_group_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('provider_id')->constrained('realm_identity_providers')->cascadeOnDelete();
            $table->string('external_group');
            $table->string('group_dn');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_provider_group_mappings');
    }
};
