<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    private static array $items = ['user', 'domain', 'group', 'role', 'committee'];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() : void
    {
        foreach (self::$items as $item){
            Schema::table($item, static function (Blueprint $table){
                $table->string('uid')->unique()->nullable();
                $table->string('domain')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() : void
    {
        foreach (self::$items as $item){
            Schema::table($item, static function(Blueprint $table){
                $table->dropColumn('uid');
                $table->dropColumn('domain');
            });
        }
    }
};
