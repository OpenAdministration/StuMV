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
        Schema::create('oauth_client_consents', function (Blueprint $table): void {
            // Remembers that a user has already consented to a client for a
            // specific set of scopes, independent of any access/refresh
            // token's lifetime - Laravel\Passport\Http\Controllers\AuthorizationController::hasGrantedScopes()
            // only considers currently-active tokens, which would force a
            // fresh consent prompt on every access-token expiry even though
            // nothing about the actual grant changed. Populated by
            // App\Listeners\RecordOidcClientConsent whenever a real access
            // token is issued; cleared by
            // App\Livewire\Oidc\EditOidcClient::save() whenever a client's
            // scopes actually change, which is the only thing that should
            // force a fresh prompt.
            $table->id();
            $table->uuid('client_id');
            $table->foreignId('user_id')->constrained('user')->cascadeOnDelete();
            $table->json('scopes');
            $table->timestamps();

            $table->unique(['client_id', 'user_id']);
            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_client_consents');
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
