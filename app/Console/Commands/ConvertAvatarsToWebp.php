<?php

namespace App\Console\Commands;

use App\Models\ProfilePicture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;

class ConvertAvatarsToWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:convert-avatars-to-webp {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time migration: re-encodes every stored profile picture avatar from JPEG to WebP. ProfilePicture.file_id stays a bare UUID either way (the extension is implied by convention, not stored), so no database changes are needed.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Running in --dry-run mode - no files will be written or deleted.');
        }

        $disk = Storage::disk('public');
        $converted = 0;
        $skipped = 0;

        ProfilePicture::query()->orderBy('id')->get()->each(function (ProfilePicture $picture) use ($disk, $dryRun, &$converted, &$skipped): void {
            $jpgPath = 'avatars/'.$picture->file_id.'.jpg';
            $webpPath = 'avatars/'.$picture->file_id.'.webp';

            if ($disk->exists($webpPath)) {
                $this->comment("  |-> {$picture->file_id}: already converted, skipping");
                $skipped++;

                return;
            }

            if (! $disk->exists($jpgPath)) {
                $this->warn("  ! {$picture->file_id}: no .jpg file found at $jpgPath, skipping");
                $skipped++;

                return;
            }

            $this->comment("  |-> {$picture->file_id}: converting");

            if (! $dryRun) {
                $webpBytes = Image::fromPath($disk->path($jpgPath))->toWebp()->toBytes();
                $disk->put($webpPath, $webpBytes);
                $disk->delete($jpgPath);
            }

            $converted++;
        });

        $this->info("Converted: $converted, skipped: $skipped.");

        return self::SUCCESS;
    }
}
