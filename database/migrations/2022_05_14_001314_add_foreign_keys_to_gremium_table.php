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
        Schema::table('gremium', function (Blueprint $table) {
            $table->foreign(['parent_gremium_id'], 'fk_gremien_parentGremium')->references(['id'])->on('gremium')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign(['realm_uid'], 'fk_gremien_belongingRealm')->references(['uid'])->on('realm')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gremium', function (Blueprint $table) {
            $table->dropForeign('fk_gremien_parentGremium');
            $table->dropForeign('fk_gremien_belongingRealm');
        });
    }
};
