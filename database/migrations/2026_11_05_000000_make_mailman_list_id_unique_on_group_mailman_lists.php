<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Refuse rather than guess which of several groups should keep a list.
        $duplicates = DB::table('group_mailman_lists')
            ->select('mailman_list_id')
            ->groupBy('mailman_list_id')
            ->havingRaw('count(*) > 1')
            ->pluck('mailman_list_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Several groups are mapped to these Mailman lists - delete all but one mapping each, then migrate again: '.$duplicates->implode(', '));
        }

        Schema::table('group_mailman_lists', function (Blueprint $table): void {
            $table->dropUnique(['group_dn', 'mailman_list_id']);
            $table->unique('mailman_list_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_mailman_lists', function (Blueprint $table): void {
            $table->dropUnique(['mailman_list_id']);
            $table->unique(['group_dn', 'mailman_list_id']);
        });
    }
};
