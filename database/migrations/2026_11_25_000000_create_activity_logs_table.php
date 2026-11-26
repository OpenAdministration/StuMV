<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('realm')->nullable();
            // user (entryUUID) or identity_provider (its id), both null for the system
            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            // entryUUID for LDAP entries, the primary key for database rows
            $table->string('subject_id')->nullable();
            // the subject's name at the time, still readable once it is deleted
            $table->string('subject_label')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['realm', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
