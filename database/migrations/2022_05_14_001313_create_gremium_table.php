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
        Schema::create('gremium', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 128);
            $table->string('realm_uid', 32)->index('gremien_realms_uid_fk');
            $table->integer('parent_gremium_id')->nullable()->index('gremien_gremien_id_fk');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gremium');
    }
};
