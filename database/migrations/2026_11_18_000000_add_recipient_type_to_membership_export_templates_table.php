<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_export_templates', function (Blueprint $table): void {
            $table->string('recipient_type')->default('other')->after('date_format');
        });
    }

    public function down(): void
    {
        Schema::table('membership_export_templates', function (Blueprint $table): void {
            $table->dropColumn('recipient_type');
        });
    }
};
