<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->boolean('letter_enabled')->default(false)->after('website');
            $table->json('letter_recipient')->nullable()->after('letter_enabled');
            $table->string('letter_sender_email')->nullable()->after('letter_sender_unit');
            $table->string('letter_signer_function')->nullable()->after('letter_sender_email');
        });
    }

    public function down(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->dropColumn(['letter_enabled', 'letter_recipient', 'letter_sender_email', 'letter_signer_function']);
        });
    }
};
