<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_mailman_list_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_mailman_list_id')->constrained('group_mailman_lists')->cascadeOnDelete();
            $table->string('role');
            $table->string('group_dn');
            $table->timestamps();
            $table->unique(['group_mailman_list_id', 'role', 'group_dn'], 'group_mailman_list_groups_unique');
        });

        DB::table('group_mailman_lists')->orderBy('id')->each(function (object $mapping): void {
            DB::table('group_mailman_list_groups')->insert([
                'group_mailman_list_id' => $mapping->id,
                'role' => 'member',
                'group_dn' => $mapping->group_dn,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('group_mailman_lists', function (Blueprint $table): void {
            $table->dropColumn('group_dn');
        });
    }

    public function down(): void
    {
        Schema::table('group_mailman_lists', function (Blueprint $table): void {
            $table->string('group_dn')->default('')->after('realm');
        });

        DB::table('group_mailman_list_groups')->where('role', 'member')->orderBy('id')->each(function (object $group): void {
            DB::table('group_mailman_lists')->where('id', $group->group_mailman_list_id)->where('group_dn', '')->update(['group_dn' => $group->group_dn]);
        });

        Schema::dropIfExists('group_mailman_list_groups');
    }
};
