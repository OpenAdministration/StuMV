<?php

namespace App\Jobs\Oidc;

use App\Models\PassportClient;
use App\Services\Oidc\BackChannelLogoutTokenBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBackChannelLogoutNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PassportClient $client,
        public string $userId,
    ) {}

    /**
     * A client's endpoint being slow, unreachable, or erroring must never
     * break the user's own logout (see AuthenticatedSessionController::destroy(),
     * which dispatches this job) - log and move on rather than retry, since
     * the spec doesn't require guaranteed delivery.
     */
    public function handle(BackChannelLogoutTokenBuilder $tokenBuilder): void
    {
        $logoutToken = $tokenBuilder->build($this->client, $this->userId);

        try {
            Http::asForm()
                ->timeout(5)
                ->post($this->client->back_channel_logout_uri, [
                    'logout_token' => $logoutToken->toString(),
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('Back-channel logout notification failed', [
                'client_id' => $this->client->getKey(),
                'uri' => $this->client->back_channel_logout_uri,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
