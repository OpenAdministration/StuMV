<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfilePicture extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'profile_pictures';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user',
        'realm',
        'file_id',
    ];
}
