<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Environment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name'
    ];

    public function dimensions(): HasMany
    {
        return $this->hasMany(Dimension::class);
    }
}
