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
        Schema::table('realm_identity_providers', function (Blueprint $table) {
            $table->json('extra_authorize_params')->nullable()->after('groups_claim');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('realm_identity_providers', function (Blueprint $table) {
            $table->dropColumn('extra_authorize_params');
        });
    }
};
