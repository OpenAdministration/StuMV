<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('group_mailman_lists', function (Blueprint $table): void {
            $table->boolean('enabled')->default(true)->after('mailman_list_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_mailman_lists', function (Blueprint $table): void {
            $table->dropColumn('enabled');
        });
    }
};
