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
        Schema::create('invitation_role_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            // Staged role assignment, identified the same way
            // role_user_relation identifies a role - committee_dn + role_cn.
            // Can't be a real role_user_relation row yet: its composite FK to
            // (username, realm) requires the invitee's user row to already
            // exist, which it doesn't until they accept the invitation.
            $table->string('committee_dn');
            $table->string('role_cn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_role_selections');
    }
};
