<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->string('letter_sender_name')->nullable()->after('letter_recipient');
        });
    }

    public function down(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->dropColumn('letter_sender_name');
        });
    }
};
