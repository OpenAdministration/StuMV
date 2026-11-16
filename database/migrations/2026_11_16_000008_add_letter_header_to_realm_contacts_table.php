<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->string('letter_header_title')->nullable()->after('letter_recipient');
            $table->string('letter_header_subtitle')->nullable()->after('letter_header_title');
        });
    }

    public function down(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->dropColumn(['letter_header_title', 'letter_header_subtitle']);
        });
    }
};
