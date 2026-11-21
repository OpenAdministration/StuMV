<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailman_domain_realms', function (Blueprint $table): void {
            $table->id();
            $table->string('mail_host');
            $table->string('realm')->index();
            $table->timestamps();
            $table->unique(['mail_host', 'realm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailman_domain_realms');
    }
};
