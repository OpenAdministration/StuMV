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
        Schema::table('role_user_relation', function (Blueprint $table) {
            $table->foreign(['username'], 'fk_role_user_user')->references(['username'])->on('user')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('domain', function (Blueprint $table) {
            $table->dropForeign('fk_domain_realm');
        });
        Schema::table('role_user_relation', function (Blueprint $table) {
            $table->dropForeign('fk_role_user_user');
            $table->dropForeign('fk_role_user_role');
        });
    }
};
