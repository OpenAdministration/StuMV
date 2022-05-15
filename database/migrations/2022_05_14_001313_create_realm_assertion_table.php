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
        Schema::create('realm_assertion', function (Blueprint $table) {
            $table->integer('user_id')->index('realm_assertion_userid');
            $table->string('realm_uid', 32)->index('fk_realm_assertions_realm');
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
        Schema::dropIfExists('realm_assertion');
    }
};
