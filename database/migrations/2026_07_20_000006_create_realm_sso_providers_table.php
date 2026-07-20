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
        Schema::create('realm_sso_providers', function (Blueprint $table) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realm_sso_providers');
    }
};
