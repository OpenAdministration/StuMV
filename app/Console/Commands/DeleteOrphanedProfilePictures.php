<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\ProfilePicture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * A ProfilePicture row (user, realm, file_id) has no FK to LDAP - it's just
 * matched up at read time (see App\Livewire\Profile\Picture). Once the LDAP
 * entry it belongs to is gone (deleted directly, or moved to another realm
 * without the picture following it), the row and its stored file are just
 * dead weight - unreachable through the app, but never cleaned up on their
 * own.
 */
class DeleteOrphanedProfilePictures extends Command
{
    protected $signature = 'app:delete-orphaned-profile-pictures {--dry-run}';

    protected $description = 'Deletes every profile picture (database row and stored file) whose (user, realm) no longer has a matching LDAP entry.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Running in --dry-run mode - nothing will be deleted.');
        }

        $disk = Storage::disk('public');
        $deleted = 0;
        $skipped = 0;

        ProfilePicture::query()->orderBy('id')->get()->each(function (ProfilePicture $picture) use ($disk, $dryRun, &$deleted, &$skipped): void {
            if (empty($picture->realm)) {
                $this->warn("  ! {$picture->user}: no realm recorded, can't verify, skipping");
                $skipped++;

                return;
            }

            $realm = Community::findByUid($picture->realm);

            if ($realm === null) {
                $this->warn("  ! {$picture->user}: realm \"{$picture->realm}\" no longer exists, can't verify, skipping");
                $skipped++;

                return;
            }

            if (LdapUser::query()->in($realm->peopleDn())->where('uid', '=', $picture->user)->exists()) {
                return;
            }

            $this->comment("  |-> {$picture->user} ({$picture->realm}): no matching LDAP entry, deleting");

            if (! $dryRun) {
                $disk->delete('avatars/'.$picture->file_id.'.webp');
                $picture->delete();
            }

            $deleted++;
        });

        $this->info("Deleted: $deleted, skipped: $skipped.");

        return self::SUCCESS;
    }
}
