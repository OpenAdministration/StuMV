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
        Schema::create('role_assertion', function (Blueprint $table) {
            $table->integer('role_id')->nullable()->index('fk_role_assertion_role');
            $table->integer('user_id')->nullable()->index('fk_role_assertion_user');
            $table->date('from');
            $table->date('until')->nullable();
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
        Schema::dropIfExists('role_assertion');
    }
};
