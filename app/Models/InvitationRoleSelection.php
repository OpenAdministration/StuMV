<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationRoleSelection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'invitation_id',
        'committee_dn',
        'role_cn',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
