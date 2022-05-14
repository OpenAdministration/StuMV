<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->renameColumn('name', 'fullName');
            $table->string('username', 32)->unique('user_username_uindex');
            $table->string('phone', 32)->nullable();
            $table->string('iban', 50)->nullable();
            $table->string('adresse', 256)->nullable();
            $table->string('profilePic', 32)->nullable()->unique('profilePic');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('fullName', 'name');
            $table->dropColumn(['username', 'phone', 'iban', 'adresse', 'profilePic']);
        });
    }
};
