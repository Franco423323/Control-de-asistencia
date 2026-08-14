<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'personal';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => 'activo',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombres',
        'apellidos',
        'dni',
        'cargo',
        'estado',
    ];

    public function rostrosEncodings(): HasMany
    {
        return $this->hasMany(RostroEncoding::class);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }
}
