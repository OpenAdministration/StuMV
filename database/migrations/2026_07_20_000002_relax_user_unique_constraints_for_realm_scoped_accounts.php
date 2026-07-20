<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Both `email` and `username` were globally unique on the `user` table -
     * correct under the old one-account-per-person model, but no longer:
     * the same username (and potentially the same email, e.g. the "admin"
     * dual-account case) can now legitimately belong to independent accounts
     * in different realms. `username` becomes unique per realm instead
     * (still the actually-intended invariant); `email` becomes a plain,
     * non-unique index - the same email address is explicitly allowed to
     * register into more than one realm.
     */
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->dropUnique(['username']);
            $table->index('email');
        });

        Schema::table('user', function (Blueprint $table): void {
            $table->unique(['username', 'realm']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->dropUnique(['username', 'realm']);
            $table->dropIndex(['email']);
        });

        Schema::table('user', function (Blueprint $table): void {
            $table->unique('email');
            $table->unique('username');
        });
    }
};
