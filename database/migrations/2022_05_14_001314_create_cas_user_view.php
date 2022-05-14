<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("CREATE VIEW `cas_user` AS select `user_management`.`user`.`username` AS `username`,replace(`user_management`.`user`.`password`,'$2y$','$2a$') AS `password` from `user_management`.`user`");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS `cas_user`");
    }
};
