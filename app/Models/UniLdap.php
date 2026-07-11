<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniLdap extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'unildap';

    /**
     * @var array
     */
    protected $fillable = [
        'realm',
        'host',
        'members_base',
    ];
}
