<?php

namespace App\Models;

use App\Models\Concerns\RecordsActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class RealmBranding extends Model
{
    use HasFactory;
    use RecordsActions;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'realm_branding';

    /**
     * @var array
     */
    protected $fillable = [
        'realm',
        'logo_id',
        'background_id',
        'login_notice_enabled',
        'login_notice',
    ];

    protected function casts(): array
    {
        return [
            'login_notice_enabled' => 'boolean',
        ];
    }

    public static function forRealm(?string $realmUid): ?self
    {
        if (! $realmUid) {
            return null;
        }

        return static::where('realm', $realmUid)->first();
    }

    /**
     * Sanitized notice HTML for the login page, or null when disabled or empty.
     */
    public function loginNoticeHtml(): ?string
    {
        if (! $this->login_notice_enabled) {
            return null;
        }

        $html = self::sanitizeNotice($this->login_notice);

        return trim(strip_tags($html)) === '' ? null : $html;
    }

    public static function sanitizeNotice(?string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceAttribute('a', 'target', '_blank')
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li'] as $element) {
            $config = $config->allowElement($element);
        }

        $config = $config->allowElement('a', ['href']);

        return new HtmlSanitizer($config)->sanitize((string) $html);
    }

    protected function activityLogName(): string
    {
        return 'realm_branding';
    }
}
