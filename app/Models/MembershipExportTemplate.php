<?php

namespace App\Models;

use App\Enums\ExportDateFormat;
use App\Enums\LetterRecipient;
use App\Enums\LetterSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** What a member can pick when exporting their memberships, set up by the realm admins. */
class MembershipExportTemplate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'realm',
        'name',
        'with_letter',
        'sender',
        'date_format',
        'recipient_type',
        'recipient',
        'header_title',
        'header_subtitle',
        'sender_name',
        'sender_unit',
        'sender_building',
        'sender_room',
        'sender_email',
        'sender_phone',
        'sender_website',
        'subject',
        'body',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'with_letter' => false,
        'sender' => 'realm',
        'date_format' => 'Y-m-d',
        'recipient_type' => 'other',
    ];

    protected function casts(): array
    {
        return [
            'with_letter' => 'boolean',
            'sender' => LetterSender::class,
            'date_format' => ExportDateFormat::class,
            'recipient_type' => LetterRecipient::class,
            'recipient' => 'array',
        ];
    }

    /** @param  Builder<self>  $query */
    protected function scopeForRealm(Builder $query, string $realmUid): void
    {
        $query->where('realm', $realmUid)->orderBy('name');
    }
}
