<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property int $id
 * @property string $realm_uid
 * @property bool $active_mail
 * @property bool $for_registration
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
    protected $fillable = ['name', 'realm_uid', 'for_registration'];

    /**
     * @return BelongsTo
     */
    public function realm(): Relation
    {
        return $this->belongsTo(Realm::class);
    }
}
