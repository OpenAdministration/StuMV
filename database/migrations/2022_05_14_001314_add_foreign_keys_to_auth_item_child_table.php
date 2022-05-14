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
        Schema::table('auth_item_child', function (Blueprint $table) {
            $table->foreign(['parent'], 'auth_item_child_ibfk_1')->references(['name'])->on('auth_item')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign(['child'], 'auth_item_child_ibfk_2')->references(['name'])->on('auth_item')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('auth_item_child', function (Blueprint $table) {
            $table->dropForeign('auth_item_child_ibfk_1');
            $table->dropForeign('auth_item_child_ibfk_2');
        });
    }
};
