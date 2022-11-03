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
        Schema::table('committee', function (Blueprint $table) {
            $table->foreign(['parent_committee_id'], 'fk_committee_committee')->references(['id'])->on('committee')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign(['realm_uid'], 'fk_committee_realm')->references(['uid'])->on('realm')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
        Schema::table('domain', function (Blueprint $table) {
            $table->foreign(['realm_uid'], 'fk_domain_realm')->references(['uid'])->on('realm')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
        Schema::table('group_role_relation', function (Blueprint $table) {
            $table->foreign(['group_id'], 'fk_group_role_group')->references(['id'])->on('group');
            $table->foreign(['role_id'], 'fk_group_role_role')->references(['id'])->on('role');
        });
        Schema::table('group', function (Blueprint $table) {
            $table->foreign(['realm_uid'], 'fk_group_realm')->references(['uid'])->on('realm');
        });
        Schema::table('realm_admin_relation', function (Blueprint $table) {
            $table->foreign(['user_id'], 'fk_realm_admin_user')->references(['id'])->on('user');
            $table->foreign(['realm_uid'], 'fk_realm_admin_realm')->references(['uid'])->on('realm');
        });
        Schema::table('realm_user_relation', function (Blueprint $table) {
            $table->foreign(['user_id'], 'fk_realm_user_user')->references(['id'])->on('user');
            $table->foreign(['realm_uid'], 'fk_realm_user_realm')->references(['uid'])->on('realm');
        });
        Schema::table('role_user_relation', function (Blueprint $table) {
            $table->foreign(['user_id'], 'fk_role_user_user')->references(['id'])->on('user');
            $table->foreign(['role_id'], 'fk_role_user_role')->references(['id'])->on('role');
        });
        Schema::table('role', function (Blueprint $table) {
            $table->foreign(['committee_id'], 'fk_role_committee')->references(['id'])->on('committee')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('committee', function (Blueprint $table) {
            $table->dropForeign('fk_committee_committee');
            $table->dropForeign('fk_committee_realm');
        });
        Schema::table('domain', function (Blueprint $table) {
            $table->dropForeign('fk_domain_realm');
        });
        Schema::table('group_role_relation', function (Blueprint $table) {
            $table->dropForeign('fk_group_role_group');
            $table->dropForeign('fk_group_role_role');
        });
        Schema::table('group', function (Blueprint $table) {
            $table->dropForeign('fk_group_realm');
        });
        Schema::table('realm_admin_relation', function (Blueprint $table) {
            $table->dropForeign('fk_realm_admin_user');
            $table->dropForeign('fk_realm_admin_realm');
        });
        Schema::table('realm_user_relation', function (Blueprint $table) {
            $table->dropForeign('fk_realm_user_user');
            $table->dropForeign('fk_realm_user_realm');
        });
        Schema::table('role_user_relation', function (Blueprint $table) {
            $table->dropForeign('fk_role_user_user');
            $table->dropForeign('fk_role_user_role');
        });
        Schema::table('role', function (Blueprint $table) {
            $table->dropForeign('fk_role_committee');
        });
    }
};
