<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Split out from 2026_07_20_000001_add_realm_to_role_user_relation_table:
     * a composite FK needs its referenced columns to already be a unique key
     * on the target table, which user(username, realm) only becomes in
     * 2026_07_20_000002_relax_user_unique_constraints_for_realm_scoped_accounts
     * - this migration has to run after that one.
     */
    public function up(): void
    {
        Schema::table('role_user_relation', function (Blueprint $table): void {
            $table->foreign(['username', 'realm'], 'fk_role_user_user')
                ->references(['username', 'realm'])->on('user')
                ->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_user_relation', function (Blueprint $table): void {
            $table->dropForeign('fk_role_user_user');
        });
    }
};
