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
        Schema::create('realm_admin', function (Blueprint $table) {
            $table->integer('user_id');
            $table->string('realm_uid', 16)->index('realm_admin_realm_uid_fk');

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
        Schema::dropIfExists('realm_admin');
    }
};
