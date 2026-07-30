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
        Schema::table('role_user_relation', function (Blueprint $table) {
            // Null means "manually granted" (or granted before this column
            // existed) - App\Support\IdentityProviderGroupRoleSync only ever
            // revokes/reactivates rows it stamped with its own provider's id,
            // so a manually-granted role is never touched just because an
            // external group mapping happens to match the same committee+role.
            $table->foreignUuid('identity_provider_id')->nullable()->after('committee_dn')
                ->constrained('realm_identity_providers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_user_relation', function (Blueprint $table) {
            $table->dropConstrainedForeignId('identity_provider_id');
        });
    }
};
