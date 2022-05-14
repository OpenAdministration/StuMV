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
        Schema::table('role_assertion', function (Blueprint $table) {
            $table->foreign(['user_id'], 'fk_role_assertion_user')->references(['id'])->on('user');
            $table->foreign(['role_id'], 'fk_role_assertion_role')->references(['id'])->on('role');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('role_assertion', function (Blueprint $table) {
            $table->dropForeign('fk_role_assertion_user');
            $table->dropForeign('fk_role_assertion_role');
        });
    }
};
