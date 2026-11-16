<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->string('bank_name')->nullable()->after('website');
            $table->string('iban', 34)->nullable()->after('bank_name');
            $table->string('bic', 11)->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('realm_contacts', function (Blueprint $table): void {
            $table->dropColumn(['bank_name', 'iban', 'bic']);
        });
    }
};
