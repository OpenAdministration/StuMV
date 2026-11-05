<?php

namespace App\Console\Commands\Mailman;

use App\Support\MailmanClient;
use Illuminate\Console\Command;
use Throwable;

class DiscardPending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailman:discard-pending
                {list_id : The Mailman list_id, e.g. newsletter.lists.example.org}
                {--moderator : Also discard requests waiting for moderator approval}
                {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discards pending subscriptions of a Mailman list (by default only those waiting for the subscriber\'s confirmation), without notifying anyone';

    public function handle(MailmanClient $mailman): int
    {
        if (! config('services.mailman.api_url')) {
            $this->error('MAILMAN_API_URL is not configured.');

            return self::FAILURE;
        }

        $listId = (string) $this->argument('list_id');
        $owners = $this->option('moderator') ? ['subscriber', 'moderator'] : ['subscriber'];

        try {
            $pending = array_filter($mailman->pendingSubscriptions($listId), fn (array $request): bool => in_array($request['owner'], $owners, true));
        } catch (Throwable $e) {
            $this->error("Could not read the pending requests of $listId: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($pending === []) {
            $this->comment("No matching pending subscriptions on $listId.");

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($pending as $email => $request) {
            $this->comment("  |-> Discard: $email (waiting for {$request['owner']})");

            if ($this->option('dry-run')) {
                continue;
            }

            try {
                $mailman->discardPendingSubscription($listId, $request['token'], $request['owner']);
            } catch (Throwable $e) {
                $failed++;
                $this->error("  |   |-> Failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->comment(($this->option('dry-run') ? 'Would discard: ' : 'Discarded: ').(count($pending) - $failed).', failed: '.$failed);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
