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
        Schema::table('auth_item', function (Blueprint $table) {
            $table->foreign(['rule_name'], 'auth_item_ibfk_1')->references(['name'])->on('auth_rule')->onUpdate('CASCADE')->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('auth_item', function (Blueprint $table) {
            $table->dropForeign('auth_item_ibfk_1');
        });
    }
};
