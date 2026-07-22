<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMailmanList extends Model
{
    protected $table = 'group_mailman_lists';

    protected $fillable = [
        'realm',
        'group_dn',
        'mailman_list_id',
    ];
}
