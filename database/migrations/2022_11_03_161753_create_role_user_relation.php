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
        Schema::create('role_user_relation', function (Blueprint $table) {
            $table->id();
            $table->string('role_cn');
            $table->string('committee_dn');
            $table->string('username');
            $table->date('from');
            $table->date('until')->nullable();
            $table->date('decided')->nullable();
            $table->text('comment')->nullable();
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
        Schema::dropIfExists('role_user_relation');
    }
};
