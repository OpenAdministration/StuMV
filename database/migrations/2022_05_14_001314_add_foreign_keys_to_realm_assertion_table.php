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
        Schema::table('realm_assertion', function (Blueprint $table) {
            $table->foreign(['user_id'], 'fk_realm_assertions_user')->references(['id'])->on('user');
            $table->foreign(['realm_uid'], 'fk_realm_assertions_realm')->references(['uid'])->on('realm');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('realm_assertion', function (Blueprint $table) {
            $table->dropForeign('fk_realm_assertions_user');
            $table->dropForeign('fk_realm_assertions_realm');
        });
    }
};
