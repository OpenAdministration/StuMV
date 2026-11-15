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
     * Split out from 2026_07_20_000001_add_realm_to_role_user_relation_table:
     * a composite FK needs its referenced columns to already be a unique key
     * on the target table, which user(username, realm) only becomes in
     * 2026_07_20_000002_relax_user_unique_constraints_for_realm_scoped_accounts
     * - this migration has to run after that one.
     */
    public function up(): void
    {
        $this->backfillUserRealms();

        Schema::table('role_user_relation', function (Blueprint $table): void {
            $table->foreign(['username', 'realm'], 'fk_role_user_user')
                ->references(['username', 'realm'])->on('user')
                ->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Every user row predates the realm column and still has realm = NULL, so
     * the FK below would reject every membership. Each (username, realm) a
     * membership points at gets a row: the unscoped one takes the first realm,
     * further realms get a copy without uid - app:split-people-by-realm links
     * each copy to the LDAP entry it creates for that realm.
     */
    private function backfillUserRealms(): void
    {
        $pairs = DB::table('role_user_relation')
            ->whereNotNull('realm')
            ->select('username', 'realm')
            ->distinct()
            ->orderBy('username')
            ->orderBy('realm')
            ->get();

        foreach ($pairs as $pair) {
            $rows = DB::table('user')->where('username', $pair->username);

            if ((clone $rows)->where('realm', $pair->realm)->exists()) {
                continue;
            }

            $unscoped = (clone $rows)->whereNull('realm')->first();

            if ($unscoped !== null) {
                DB::table('user')->where('id', $unscoped->id)->update(['realm' => $pair->realm]);

                continue;
            }

            $template = (clone $rows)->orderBy('id')->first();

            if ($template === null) {
                continue;
            }

            $copy = (array) $template;
            unset($copy['id']);

            DB::table('user')->insert([
                ...$copy,
                'uid' => null,
                'realm' => $pair->realm,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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
