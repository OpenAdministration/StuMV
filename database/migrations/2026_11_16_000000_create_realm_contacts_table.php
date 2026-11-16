<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postal and contact details of a realm for the letterhead of exported
     * cover letters, plus the defaults those letters start out with.
     */
    public function up(): void
    {
        Schema::create('realm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('realm')->unique();
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('letter_sender_unit')->nullable();
            $table->string('letter_subject')->nullable();
            $table->text('letter_body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realm_contacts');
    }
};
