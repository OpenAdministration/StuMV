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
        Schema::create('user_additional_emails', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('realm');
            $table->string('address');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['address', 'realm']);
            $table->index(['username', 'realm']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_additional_emails');
    }
};
