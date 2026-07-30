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
        Schema::create('identity_provider_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('provider_id')->constrained('realm_identity_providers')->cascadeOnDelete();
            $table->string('external_sub');
            $table->string('session_id')->unique();
            $table->timestamps();

            $table->index(['provider_id', 'external_sub']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_provider_sessions');
    }
};
