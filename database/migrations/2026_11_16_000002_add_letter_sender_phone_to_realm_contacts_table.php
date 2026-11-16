<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->string('letter_sender_phone')->nullable()->after('letter_sender_email');
        });
    }

    public function down(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->dropColumn('letter_sender_phone');
        });
    }
};
