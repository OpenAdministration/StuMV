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
        DB::statement("CREATE VIEW `cas_attributes` AS select `user_management`.`user`.`username` AS `username`,'realm' AS `attribute`,`ra`.`realm_uid` AS `value` from (`user_management`.`user` left join `user_management`.`realm_assertion` `ra` on(`ra`.`user_id` = `user_management`.`user`.`id`)) union select `user_management`.`user`.`username` AS `username`,'groups' AS `attribute`,`g`.`name` AS `value` from (((`user_management`.`user` left join `user_management`.`role_assertion` `ra` on(`ra`.`user_id` = `user_management`.`user`.`id`)) left join `user_management`.`group_assertion` `ga` on(`ga`.`role_id` = `ra`.`role_id`)) left join `user_management`.`group` `g` on(`g`.`id` = `ga`.`group_id`)) union select `user_management`.`user`.`username` AS `username`,'gremien' AS `attribute`,`g`.`name` AS `value` from (((`user_management`.`user` left join `user_management`.`role_assertion` `ra` on(`ra`.`user_id` = `user_management`.`user`.`id`)) left join `user_management`.`role` `r` on(`ra`.`role_id` = `r`.`id`)) left join `user_management`.`gremium` `g` on(`g`.`id` = `r`.`gremium_id`))");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS `cas_attributes`");
    }
};
