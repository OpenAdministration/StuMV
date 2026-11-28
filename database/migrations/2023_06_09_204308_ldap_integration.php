<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private static array $items = ['user', 'domain'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::$items as $item) {
            Schema::table($item, static function (Blueprint $table): void {
                $table->string('uid')->unique()->nullable();
                $table->string('domain')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::$items as $item) {
            Schema::table($item, static function (Blueprint $table): void {
                $table->dropColumn('uid');
                $table->dropColumn('domain');
            });
        }
    }
};
