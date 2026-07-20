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
        Schema::table('profile_pictures', function (Blueprint $table): void {
            // profile_pictures.user is a bare username string with no realm
            // reference of its own - now that the same username can belong
            // to independent accounts in different realms, its unique()
            // constraint must become composite (user, realm) instead of
            // blocking a second account's own picture outright.
            $table->dropUnique(['user']);
            $table->string('realm')->nullable()->after('user');
        });

        Schema::table('profile_pictures', function (Blueprint $table): void {
            $table->unique(['user', 'realm']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profile_pictures', function (Blueprint $table): void {
            $table->dropUnique(['user', 'realm']);
            $table->dropColumn('realm');
        });

        Schema::table('profile_pictures', function (Blueprint $table): void {
            $table->unique('user');
        });
    }
};
