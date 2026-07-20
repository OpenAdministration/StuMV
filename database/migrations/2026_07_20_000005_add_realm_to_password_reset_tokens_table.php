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
     * `email` alone is no longer a safe primary key: the same email address
     * can legitimately belong to independent accounts in different realms
     * (see the `user_username_realm_unique` migration), and a reset token
     * must only ever be valid for the one account it was issued to. Existing
     * rows are dropped rather than backfilled - reset tokens are short-lived
     * and safe to invalidate, and there is no reliable way to attribute an
     * old row to a specific realm after the fact.
     */
    public function up(): void
    {
        DB::table('password_reset_tokens')->truncate();

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->dropPrimary();
        });

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->string('realm')->after('email');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->primary(['email', 'realm']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('password_reset_tokens')->truncate();

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->dropPrimary();
        });

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->dropColumn('realm');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->primary('email');
        });
    }
};
