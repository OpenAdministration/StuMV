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
        // LDAP group membership (uniqueMember) carries no per-member metadata
        // at all, so unlike role_user_relation there's nowhere on the LDAP
        // side to stamp "granted by this provider" - this table is that
        // record on the StuMV side instead. App\Support\IdentityProviderGroupSync
        // only ever detaches a membership it finds a grant row for here, so a
        // membership that pre-dates the mapping (or was added by an admin, or
        // by the role-derived ldap:sync-groups path) is never touched.
        Schema::create('identity_provider_group_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('provider_id')->constrained('realm_identity_providers')->cascadeOnDelete();
            $table->string('username');
            $table->string('group_dn');
            $table->timestamps();

            $table->unique(['provider_id', 'username', 'group_dn'], 'idp_group_grants_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_provider_group_grants');
    }
};
