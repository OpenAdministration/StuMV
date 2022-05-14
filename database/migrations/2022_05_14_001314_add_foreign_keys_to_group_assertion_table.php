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
        Schema::table('group_assertion', function (Blueprint $table) {
            $table->foreign(['group_id'], 'fk_group_assertions_realms')->references(['id'])->on('group');
            $table->foreign(['role_id'], 'fk_group_assertion')->references(['id'])->on('role');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_assertion', function (Blueprint $table) {
            $table->dropForeign('fk_group_assertions_realms');
            $table->dropForeign('fk_group_assertion');
        });
    }
};
