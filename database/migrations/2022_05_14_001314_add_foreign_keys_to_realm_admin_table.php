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
        Schema::table('realm_admin', function (Blueprint $table) {
            $table->foreign(['user_id'], 'realm_admin_user_id_fk')->references(['id'])->on('user');
            $table->foreign(['realm_uid'], 'realm_admin_realm_uid_fk')->references(['uid'])->on('realm');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('realm_admin', function (Blueprint $table) {
            $table->dropForeign('realm_admin_user_id_fk');
            $table->dropForeign('realm_admin_realm_uid_fk');
        });
    }
};
