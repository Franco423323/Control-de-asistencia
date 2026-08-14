<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RostroEncoding extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rostros_encodings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'personal_id',
        'encoding',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'encoding' => 'array',
    ];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }
}
