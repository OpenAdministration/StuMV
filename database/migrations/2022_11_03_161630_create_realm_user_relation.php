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
        Schema::create('realm_user_relation', function (Blueprint $table) {
            $table->bigInteger('user_id')->unsigned();
            $table->string('realm_uid', 32);
            $table->timestamps();
            $table->primary(['user_id', 'realm_uid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('realm_user_relation');
    }
};
