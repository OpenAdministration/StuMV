<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * role_user_relation.username had a single-column FK straight to
     * user.username - unsound once username alone isn't unique on `user`
     * anymore. committee_dn already unambiguously encodes the owning realm
     * (committees live under ou=Committees,ou=<realm>,ou=Communities,{base}),
     * so a `realm` column here is just making that existing, implicit fact
     * explicit and queryable, and lets the FK become a proper composite one.
     *
     * The old FK is dropped here (it would otherwise block the next
     * migration's drop of user's single-column username unique index), but
     * the new composite FK isn't added until
     * 2026_07_20_000003_add_composite_fk_to_role_user_relation_table - it
     * needs user's own composite (username, realm) unique index to exist
     * first, which the migration in between this one creates.
     */
    public function up(): void
    {
        Schema::table('role_user_relation', function (Blueprint $table): void {
            $table->dropForeign('fk_role_user_user');
            $table->string('realm')->nullable()->after('username');
        });

        // Backfill from committee_dn - a plain string column, so parsed in
        // PHP rather than relying on a DB-specific regex function.
        foreach (DB::table('role_user_relation')->select('id', 'committee_dn')->get() as $row) {
            if (preg_match('/,ou=([0-9A-Za-z_\-]+),ou=Communities,/', (string) $row->committee_dn, $matches)) {
                DB::table('role_user_relation')->where('id', $row->id)->update(['realm' => $matches[1]]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_user_relation', function (Blueprint $table): void {
            $table->dropColumn('realm');
        });

        Schema::table('role_user_relation', function (Blueprint $table): void {
            $table->foreign(['username'], 'fk_role_user_user')->references(['username'])->on('user')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }
};
