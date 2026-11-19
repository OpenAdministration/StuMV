<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\AccountDeletionMark;
use App\Models\User;
use App\Support\AccountPurger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/** Deletes the accounts marked by app:mark-inactive-accounts whose grace period ran out without a login. */
class DeleteMarkedAccounts extends Command
{
    protected $signature = 'app:delete-marked-accounts {realm? : Only delete in this realm} {--dry-run}';

    protected $description = 'Deletes accounts marked as inactive whose owners did not log in before the deadline';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->comment('Running in --dry-run mode - nothing is deleted.');
        }

        $marks = AccountDeletionMark::where('delete_after', '<', Date::now())
            ->when($this->argument('realm'), fn ($query, $realm) => $query->where('realm', $realm))
            ->get();
        $counts = ['deleted' => 0, 'kept' => 0];

        foreach ($marks as $mark) {
            $community = Community::findByUid($mark->realm);
            $entry = $community === null ? null : LdapUser::query()
                ->in($community->peopleDn())
                ->select(['*', 'pwdLastSuccess'])
                ->findByGuid($mark->ldap_uid);

            if ($community === null || $entry === null) {
                $this->line("  - {$mark->username} ({$mark->realm}): gone already, removing the mark");
                if (! $dryRun) {
                    $mark->delete();
                }

                continue;
            }

            $username = (string) $entry->getFirstAttribute('uid');
            $lastLogin = collect([
                $entry->lastSuccessfulLogin(),
                User::where('uid', $mark->ldap_uid)->value('last_login_at'),
            ])->filter()->map(fn ($date) => Date::parse($date))->max();

            if ($lastLogin !== null && $lastLogin->greaterThan($mark->notified_at)) {
                $this->line("  - {$username} ({$mark->realm}): logged in since, removing the mark");
                $counts['kept']++;
                if (! $dryRun) {
                    $mark->delete();
                }

                continue;
            }

            $this->line("  x {$username} ({$mark->realm}): no login since {$mark->notified_at->toDateString()}, deleting");
            $counts['deleted']++;
            if (! $dryRun) {
                AccountPurger::purge($community, $username);
                $mark->delete();
            }
        }

        $this->info(sprintf('%d account(s) deleted, %d kept after a login.', $counts['deleted'], $counts['kept']));

        return self::SUCCESS;
    }
}
