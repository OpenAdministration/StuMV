<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $id
 * @property string $realm_uid
 * @property boolean $active_mail
 * @property boolean $for_registration
 * @property string $name
 * @property Realm $realm
 */
class Domain extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'domain';

    /**
     * @var array
     */
    protected $fillable = ['name', 'realm_uid', 'active_mail', 'for_registration'];

    /**
     * @return BelongsTo
     */
    public function realm(): Relation
    {
        return $this->belongsTo(Realm::class, 'realm_uid', 'uid');
    }
}
