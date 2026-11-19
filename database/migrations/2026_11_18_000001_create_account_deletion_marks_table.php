<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_marks', function (Blueprint $table): void {
            $table->id();
            // LDAP entryUUID, the same account under any username or realm
            $table->string('ldap_uid')->unique();
            $table->string('realm')->index();
            $table->string('username');
            $table->string('email');
            $table->timestamp('notified_at');
            $table->timestamp('delete_after');
            $table->timestamps();
        });

        // logins via an external identity provider never bind against LDAP
        Schema::table('user', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->dropColumn('last_login_at');
        });

        Schema::dropIfExists('account_deletion_marks');
    }
};
