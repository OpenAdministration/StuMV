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
        DB::statement("CREATE VIEW `cas_attributes` AS
        select `user`.`username` AS `username`,'realm' AS `attribute`,`ra`.`realm_uid` AS `value`
        from (`user`
        left join `realm_assertion` `ra` on(`ra`.`user_id` = `user`.`id`))
        union
        select `user`.`username` AS `username`,'groups' AS `attribute`,`g`.`name` AS `value`
        from (((`user`
        left join `role_assertion` `ra` on(`ra`.`user_id` = `user`.`id`))
        left join `group_assertion` `ga` on(`ga`.`role_id` = `ra`.`role_id`))
        left join `group` `g` on(`g`.`id` = `ga`.`group_id`))
        union
        select `user`.`username` AS `username`,'gremien' AS `attribute`,`g`.`name` AS `value`
        from (((`user` left join `role_assertion` `ra` on(`ra`.`user_id` = `user`.`id`))
        left join `role` `r` on(`ra`.`role_id` = `r`.`id`))
        left join `gremium` `g` on(`g`.`id` = `r`.`gremium_id`))");
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
