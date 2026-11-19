<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\AccountDeletionMark;
use App\Models\User;
use App\Notifications\InactiveAccountNotice;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

/**
 * Asks the owners of accounts without a login for ACCOUNT_INACTIVE_AFTER_DAYS
 * by email whether they still need them, and marks those accounts for
 * deletion. A login since then removes the mark again.
 */
class MarkInactiveAccounts extends Command
{
    protected $signature = 'app:mark-inactive-accounts {realm? : Only check this realm} {--dry-run}';

    protected $description = 'Emails the owners of inactive accounts and marks those accounts for deletion';

    public function handle(): int
    {
        $afterDays = config('accounts.inactive_after_days');
        if ($afterDays === null) {
            $this->comment('ACCOUNT_INACTIVE_AFTER_DAYS is not set, nothing to do.');

            return self::SUCCESS;
        }

        $communities = $this->argument('realm') !== null
            ? [Community::findByUid($this->argument('realm'))]
            : Community::query()->get()->all();

        if ($communities === [null]) {
            $this->error('Unknown realm: '.$this->argument('realm'));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->comment('Running in --dry-run mode - no emails are sent and nothing is marked.');
        }

        $cutoff = Date::now()->subDays((int) $afterDays);
        $counts = ['marked' => 0, 'unmarked' => 0, 'without_email' => 0];

        foreach ($communities as $community) {
            $this->checkRealm($community, $cutoff, $dryRun, $counts);
        }

        $this->info(sprintf(
            '%d account(s) marked, %d mark(s) removed, %d inactive account(s) without an email address skipped.',
            $counts['marked'],
            $counts['unmarked'],
            $counts['without_email'],
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array{marked: int, unmarked: int, without_email: int}  $counts
     */
    private function checkRealm(Community $community, CarbonInterface $cutoff, bool $dryRun, array &$counts): void
    {
        $realm = $community->getShortCode();
        $marks = AccountDeletionMark::where('realm', $realm)->get()->keyBy('ldap_uid');
        $lastLogins = User::where('realm', $realm)->whereNotNull('last_login_at')->pluck('last_login_at', 'uid');
        $seen = [];

        $entries = LdapUser::query()
            ->in($community->peopleDn())
            ->select(['*', 'pwdLastSuccess', 'pwdAccountLockedTime', 'createTimestamp'])
            ->get();

        foreach ($entries as $entry) {
            $guid = (string) $entry->getConvertedGuid();
            $seen[] = $guid;
            $username = (string) $entry->getFirstAttribute('uid');

            $lastLogin = collect([$entry->lastSuccessfulLogin(), $lastLogins[$guid] ?? null])->filter()->max();
            // LdapRecord casts createTimestamp to a date already
            $created = $entry->getFirstAttribute('createTimestamp');
            $lastActivity = $lastLogin ?? ($created instanceof CarbonInterface ? $created : null);

            $mark = $marks[$guid] ?? null;
            if ($mark !== null) {
                // a login elsewhere (another service binding against LDAP) counts too
                if ($lastLogin !== null && $lastLogin->greaterThan($mark->notified_at)) {
                    $this->line("  - {$username} ({$realm}): logged in since, removing the mark");
                    $counts['unmarked']++;
                    if (! $dryRun) {
                        $mark->delete();
                    }
                }

                continue;
            }

            // a locked account cannot log in to keep itself
            if ($entry->hasAttribute('pwdAccountLockedTime') || $lastActivity === null || $lastActivity->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            $email = (string) $entry->getFirstAttribute('mail');
            if ($email === '') {
                $this->warn("  ! {$username} ({$realm}): inactive, but no email address to ask");
                $counts['without_email']++;

                continue;
            }

            $this->line("  + {$username} ({$realm}): inactive since {$lastActivity->toDateString()}, asking {$email}");
            $counts['marked']++;
            if ($dryRun) {
                continue;
            }

            $deleteAfter = Date::today()->addDays((int) config('accounts.deletion_grace_days'))->endOfDay();
            Notification::route('mail', $email)->notify(new InactiveAccountNotice(
                (string) ($entry->getFirstAttribute('cn') ?: $username),
                $username,
                (string) ($community->getFirstAttribute('description') ?: $realm),
                route('realm.login', ['realm' => $realm]),
                $lastLogin,
                $deleteAfter,
            ));

            AccountDeletionMark::create([
                'ldap_uid' => $guid,
                'realm' => $realm,
                'username' => $username,
                'email' => $email,
                'notified_at' => Date::now(),
                'delete_after' => $deleteAfter,
            ]);
        }

        // the entry is gone, so is the account
        if (! $dryRun) {
            AccountDeletionMark::where('realm', $realm)->whereNotIn('ldap_uid', $seen)->delete();
        }
    }
}
